<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\BridgingSep;
use App\Models\RegPeriksa;
use App\Models\PemeriksaanRalan;
use App\Models\CodingCasemix;
use App\Models\ClinicalNotesSnomed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CodingCasemixController extends Controller
{
    /**
     * Get queue kunjungan yang perlu di-coding
     */
    public function getQueueByPatient(Request $request)
    {
        $validated = $request->validate([
            'no_rkm_medis' => 'required|string',
            'status' => 'nullable|string|in:all,pending,draft,verified,final'
        ]);

        $noRkmMedis = $validated['no_rkm_medis'];
        $status = $validated['status'] ?? 'all';

        try {
            // Query kunjungan pasien ini dengan relasi yang lebih sederhana
            $query = BridgingSep::with([
                'codingCasemix'
            ])
            ->where('nomr', $noRkmMedis) // Langsung filter berdasarkan nomr
            ->whereNotNull('no_rawat');

            // Filter by coding status
            if ($status === 'pending') {
                $query->doesntHave('codingCasemix');
            } elseif ($status !== 'all') {
                $query->whereHas('codingCasemix', function($q) use ($status) {
                    $q->where('status', $status);
                });
            }

            $data = $query->orderBy('tglsep', 'desc')
                         ->get();

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $data->count(),
                'patient' => [
                    'no_rkm_medis' => $noRkmMedis,
                    'total_visits' => $data->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error in getQueueByPatient: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'data' => [],
                'total' => 0,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get detail kunjungan untuk coding
     */
    public function getDetailForCoding(string $no_sep)
    {
        $bridging = BridgingSep::where('no_sep', $no_sep)->first();

        if (!$bridging || !$bridging->no_rawat) {
            return response()->json([
                'success' => false, 
                'message' => 'Nomor Rawat tidak ditemukan'
            ], 404);
        }

        $no_rawat = $bridging->no_rawat;

        $regPeriksa = RegPeriksa::with(['pasien', 'poliklinik', 'dokter'])
            ->where('no_rawat', $no_rawat)
            ->first();

        if (!$regPeriksa) {
            return response()->json([
                'success' => false, 
                'message' => 'Data registrasi tidak ditemukan'
            ], 404);
        }

        // Ambil pemeriksaan ralan (SOAP)
        $pemeriksaanRalan = PemeriksaanRalan::where('no_rawat', $no_rawat)
            ->orderBy('tgl_perawatan', 'desc')
            ->orderBy('jam_rawat', 'desc')
            ->get();

        // Check existing coding
        $existingCoding = CodingCasemix::with('clinicalNotesSnomed')
            ->where('no_sep', $no_sep)
            ->first();

        // Generate auto-suggestions
        $suggestions = $this->generateAutoSuggestions($pemeriksaanRalan);

        $data = [
            'no_sep' => $no_sep,
            'no_rawat' => $no_rawat,
            'registrasi' => $regPeriksa,
            'pemeriksaan_ralan' => $pemeriksaanRalan,
            'existing_coding' => $existingCoding,
            'suggestions' => $suggestions
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Expand ValueSet - FHIR compliant
     */
    public function expandValueSet(Request $request)
    {
        $url = $request->input('url');
        $filter = $request->input('filter');
        $count = $request->input('count', 10);

        if (empty($url)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'required',
                    'diagnostics' => 'Parameter "url" is required'
                ]]
            ], 400);
        }

        // Parse SNOMED CT URL - support both old and new format
        if (strpos($url, 'http://snomed.info/sct') === false) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'not-supported',
                    'diagnostics' => 'Only SNOMED CT ValueSets are supported'
                ]]
            ], 400);
        }

        // Handle different SNOMED URL formats
        // Format: http://snomed.info/sct?fhir_vs or http://snomed.info/sct?fhir_vs=refset/123456789
        $snomedParams = [];
        if (strpos($url, '?fhir_vs') !== false) {
            // Extract any additional parameters from the URL
            $urlParts = parse_url($url);
            if (isset($urlParts['query'])) {
                parse_str($urlParts['query'], $snomedParams);
            }
        }

        // Jika tidak ada filter, gunakan filter default atau kembalikan error
        if (empty($filter)) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'required',
                    'diagnostics' => 'Parameter "filter" is required for ValueSet expansion'
                ]]
            ], 400);
        }

        // Gunakan pencarian langsung ke Snowstorm tanpa melalui fungsi searchSnomed
        $snowstormUrl = rtrim(config('services.snowstorm.url', 'http://localhost:8080'), '/');
        
        $params = [
            'term' => $filter,
            'language' => 'en',
            'active' => 'true',
            'limit' => $count
        ];

        try {
            // Coba endpoint descriptions dulu
            $response = Http::timeout(10)->get("{$snowstormUrl}/MAIN/descriptions", $params);
            
            if (!$response->successful()) {
                // Fallback ke endpoint concepts
                $response = Http::timeout(10)->get("{$snowstormUrl}/browser/MAIN/concepts", [
                    'term' => $filter,
                    'limit' => $count
                ]);
            }

            if (!$response->successful()) {
                return response()->json([
                    'resourceType' => 'OperationOutcome',
                    'issue' => [[
                        'severity' => 'error',
                        'code' => 'processing',
                        'diagnostics' => 'Snowstorm API error: ' . $response->status() . ' - ' . $response->body()
                    ]]
                ], 500);
            }

            $data = $response->json();
            
            // Proses hasil dari Snowstorm
            if (isset($data['items'])) {
                // Format dari descriptions endpoint
                $results = collect($data['items'])->map(function($item) {
                    return [
                        'snomed_concept_id' => $item['concept']['conceptId'] ?? $item['conceptId'],
                        'snomed_term' => $item['term'] ?? $item['fsn']['term'] ?? 'Unknown',
                        'snomed_fsn' => $item['concept']['fsn']['term'] ?? $item['fsn']['term'] ?? null,
                        'semantic_tag' => $this->extractSemanticTag($item['concept']['fsn']['term'] ?? $item['fsn']['term'] ?? '')
                    ];
                });
            } else {
                // Format dari concepts endpoint
                $results = collect($data)->map(function($item) {
                    return [
                        'snomed_concept_id' => $item['conceptId'],
                        'snomed_term' => $item['fsn']['term'] ?? $item['pt']['term'] ?? 'Unknown',
                        'snomed_fsn' => $item['fsn']['term'] ?? null,
                        'semantic_tag' => $this->extractSemanticTag($item['fsn']['term'] ?? '')
                    ];
                });
            }

            // Convert to FHIR ValueSet expansion format
            $expansion = [
                'resourceType' => 'ValueSet',
                'id' => 'snomed-ct-expansion',
                'url' => $url,
                'status' => 'active',
                'expansion' => [
                    'identifier' => uniqid('expansion-'),
                    'timestamp' => now()->format('Y-m-d\TH:i:s\Z'),
                    'total' => $results->count(),
                    'contains' => $results->map(function($item) {
                        return [
                            'system' => 'http://snomed.info/sct',
                            'code' => (string) $item['snomed_concept_id'],
                            'display' => $item['snomed_term'],
                            'extension' => [[
                                'url' => 'http://hl7.org/fhir/StructureDefinition/valueset-expansion-subset',
                                'valueString' => $item['semantic_tag'] ?? 'other'
                            ]]
                        ];
                    })->toArray()
                ]
            ];

            return response()->json($expansion, 200, [
                'Content-Type' => 'application/fhir+json'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'processing',
                    'diagnostics' => 'Error connecting to Snowstorm: ' . $e->getMessage()
                ]]
            ], 500);
        }

        // Convert to FHIR ValueSet expansion format
        $expansion = [
            'resourceType' => 'ValueSet',
            'id' => 'snomed-ct-expansion',
            'url' => $url,
            'status' => 'active',
            'expansion' => [
                'identifier' => uniqid('expansion-'),
                'timestamp' => now()->format('Y-m-d\TH:i:s\Z'),
                'total' => count($searchData['data']),
                'contains' => array_map(function($item) {
                    return [
                        'system' => 'http://snomed.info/sct',
                        'code' => (string) $item['snomed_concept_id'],
                        'display' => $item['snomed_term'],
                        'extension' => [[
                            'url' => 'http://hl7.org/fhir/StructureDefinition/valueset-expansion-subset',
                            'valueString' => $item['semantic_tag'] ?? 'other'
                        ]]
                    ];
                }, $searchData['data'])
            ]
        ];

        // Set proper FHIR content type
        return response()->json($expansion, 200, [
            'Content-Type' => 'application/fhir+json'
        ]);
    }

    /**
     * Search SNOMED concepts
     */
    /**
     * Search SNOMED concepts (Menggunakan endpoint FHIR)
     */
    public function searchSnomed(Request $request)
    {
        $term = $request->input('term');
        $semanticTags = $request->input('semantic_tags', []); // Kita akan gunakan ini nanti
        $limit = $request->input('limit', 10);

        if (strlen($term) < 3) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // Cek synonym untuk translate Indonesia -> English
        $synonym = DB::table('text_synonyms')
            ->where('text_variant', 'LIKE', "%{$term}%")
            ->where('language', 'id')
            ->first();

        $searchTerm = $synonym ? $synonym->normalized_term : $term;

        // Ambil base URL dari .env
        $snowstormUrl = rtrim(config('services.snowstorm.url', 'http://localhost:8080'), '/');
        
        // 1. Siapkan parameter untuk endpoint FHIR
        $params = [
            'url' => 'http://snomed.info/sct?fhir_vs', // Sesuai target Anda
            'filter' => $searchTerm,                  // Sesuai target Anda
            'count' => $limit
        ];
        
        // Tambahkan filter semantic tag jika ada
        if (!empty($semanticTags)) {
             // TODO: Logika untuk menambahkan filter semanticTags ke parameter 'url'
             // Contoh: $params['url'] = $params['url'] . '&filterBy=...';
             // Untuk saat ini, Snowstorm FHIR API standar mungkin tidak langsung mendukung
             // filter semantic tag via parameter 'filter'. Ini mungkin perlu query ECL di parameter 'url'.
        }

        try {
            // 2. Ubah URL endpoint ke target FHIR Anda
            // Gunakan \$expand untuk keamanan string PHP
            $response = Http::timeout(10)->get("{$snowstormUrl}/fhir/ValueSet/\$expand", $params);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Snowstorm API error (FHIR): ' . $response->status() . ' - ' . $response->body()
                ], 500);
            }

            $data = $response->json();
            
            // 3. LOGIC PENTING: Parsing respons FHIR ValueSet
            // Ini berbeda total dari $data['items']
            $contains = $data['expansion']['contains'] ?? [];

            $results = collect($contains)->map(function($item) {
                // Ekstrak semantic tag dari 'extension' jika ada
                $semantic_tag = null;
                if (isset($item['extension'])) {
                    foreach ($item['extension'] as $ext) {
                        if ($ext['url'] === 'http://hl7.org/fhir/StructureDefinition/valueset-expansion-subset') {
                            $semantic_tag = $ext['valueString'];
                            break;
                        }
                    }
                }

                // Endpoint expand standar mungkin tidak mengembalikan FSN, jadi kita gunakan display
                $fsn = $item['display']; 

                return [
                    'snomed_concept_id' => $item['code'],
                    'snomed_term' => $item['display'],
                    'snomed_fsn' => $fsn, 
                    'semantic_tag' => $semantic_tag ?? $this->extractSemanticTag($fsn) // fallback
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $results,
                'search_term_used' => $searchTerm,
                'synonym_matched' => $synonym !== null,
                'api_used' => 'fhir/ValueSet/$expand' // Menandakan API baru
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save coding
     */
    public function saveCoding(Request $request)
    {
        $validated = $request->validate([
            'no_sep' => 'required|string',
            'no_rawat' => 'required|string',
            'mappings' => 'required|array|min:1',
            'mappings.*.no_rawat' => 'required|string',
            'mappings.*.tgl_perawatan' => 'required|date',
            'mappings.*.jam_rawat' => 'required',
            'mappings.*.source_field' => 'required|in:keluhan,pemeriksaan,penilaian,rtl,instruksi,evaluasi',
            'mappings.*.source_text' => 'required|string',
            'mappings.*.snomed_concept_id' => 'required|integer',
            'mappings.*.snomed_term' => 'required|string',
            'mappings.*.concept_type' => 'required|in:symptom,finding,disorder,procedure,body_structure,other',
            'status' => 'required|in:draft,verified,final',
        ]);

        DB::beginTransaction();
        try {
            $coding = CodingCasemix::updateOrCreate(
                [
                    'no_sep' => $validated['no_sep'],
                    'no_rawat' => $validated['no_rawat']
                ],
                [
                    'tgl_coding' => now(),
                    'koder_nip' => auth()->user()->nik ?? '000000',
                    'status' => $validated['status'],
                    'catatan_koder' => $request->input('catatan_koder')
                ]
            );

            // Delete existing mappings
            ClinicalNotesSnomed::where('coding_id', $coding->id)->delete();

            // Insert new mappings
            foreach ($validated['mappings'] as $mapping) {
                ClinicalNotesSnomed::create([
                    'coding_id' => $coding->id,
                    'no_rawat' => $mapping['no_rawat'],
                    'tgl_perawatan' => $mapping['tgl_perawatan'],
                    'jam_rawat' => $mapping['jam_rawat'],
                    'source_field' => $mapping['source_field'],
                    'source_text' => $mapping['source_text'],
                    'snomed_concept_id' => $mapping['snomed_concept_id'],
                    'snomed_term' => $mapping['snomed_term'],
                    'snomed_fsn' => $mapping['snomed_fsn'] ?? null,
                    'concept_type' => $mapping['concept_type'],
                    'confidence_score' => $mapping['confidence_score'] ?? null,
                    'mapped_by' => $mapping['mapped_by'] ?? 'manual'
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Coding berhasil disimpan',
                'data' => $coding->load('clinicalNotesSnomed')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan coding: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper functions
    private function extractSemanticTag($fsn)
    {
        if (preg_match('/\(([^)]+)\)$/', $fsn, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function generateAutoSuggestions($pemeriksaanRalan)
    {
        $suggestions = [];

        foreach ($pemeriksaanRalan as $soap) {
            $fields = ['keluhan', 'pemeriksaan', 'penilaian', 'rtl'];
            
            foreach ($fields as $field) {
                if (empty($soap->$field)) continue;

                $keywords = $this->extractKeywords($soap->$field);
                
                foreach ($keywords as $keyword) {
                    $synonyms = DB::table('text_synonyms')
                        ->where('text_variant', 'LIKE', "%{$keyword}%")
                        ->where('language', 'id')
                        ->limit(2)
                        ->get();

                    foreach ($synonyms as $syn) {
                        if ($syn->snomed_concept_id) {
                            $suggestions[] = [
                                'source_field' => $field,
                                'source_text' => $keyword,
                                'suggested_term' => $syn->normalized_term,
                                'snomed_concept_id' => $syn->snomed_concept_id,
                                'concept_type' => $syn->category ?? 'other'
                            ];
                        }
                    }
                }
            }
        }

        return array_slice($suggestions, 0, 10); // Max 10 suggestions
    }

    private function extractKeywords($text)
    {
        $stopWords = ['pasien', 'mengeluh', 'merasakan', 'dengan', 'yang', 'dan', 'di', 'ke', 'dari', 'pada', 'untuk', 'sejak'];
        $words = preg_split('/[\s,\.;]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) >= 3 && !in_array($word, $stopWords);
        });

        return array_values($keywords);
    }

    /**
     * Add a single SNOMED mapping to existing clinical notes
     */
    public function addSingleMapping(Request $request)
    {
        $validated = $request->validate([
            'no_sep' => 'required|string',
            'no_rawat' => 'required|string',
            'tgl_perawatan' => 'required|date',
            'jam_rawat' => 'required',
            'source_field' => 'required|in:keluhan,pemeriksaan,penilaian,rtl,instruksi,evaluasi',
            'source_text' => 'required|string',
            'snomed_concept_id' => 'required|integer',
            'snomed_term' => 'required|string',
            'snomed_fsn' => 'nullable|string',
            'concept_type' => 'required|in:symptom,finding,disorder,procedure,body_structure,other',
            'confidence_score' => 'nullable|numeric|min:0|max:1',
            'mapped_by' => 'nullable|string|in:manual,auto,suggested'
        ]);

        DB::beginTransaction();
        try {
            // Get or create coding record
            $coding = CodingCasemix::firstOrCreate(
                [
                    'no_sep' => $validated['no_sep'],
                    'no_rawat' => $validated['no_rawat']
                ],
                [
                    'tgl_coding' => now(),
                    'koder_nip' => auth()->user()->nik ?? '000000',
                    'status' => 'draft' // Default to draft for single additions
                ]
            );

            // Check if mapping already exists
            $existingMapping = ClinicalNotesSnomed::where('coding_id', $coding->id)
                ->where('no_rawat', $validated['no_rawat'])
                ->where('tgl_perawatan', $validated['tgl_perawatan'])
                ->where('jam_rawat', $validated['jam_rawat'])
                ->where('source_field', $validated['source_field'])
                ->where('source_text', $validated['source_text'])
                ->where('snomed_concept_id', $validated['snomed_concept_id'])
                ->first();

            if ($existingMapping) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Mapping ini sudah ada'
                ], 422);
            }

            // Create new mapping
            $mapping = ClinicalNotesSnomed::create([
                'coding_id' => $coding->id,
                'no_rawat' => $validated['no_rawat'],
                'tgl_perawatan' => $validated['tgl_perawatan'],
                'jam_rawat' => $validated['jam_rawat'],
                'source_field' => $validated['source_field'],
                'source_text' => $validated['source_text'],
                'snomed_concept_id' => $validated['snomed_concept_id'],
                'snomed_term' => $validated['snomed_term'],
                'snomed_fsn' => $validated['snomed_fsn'] ?? null,
                'concept_type' => $validated['concept_type'],
                'confidence_score' => $validated['confidence_score'] ?? null,
                'mapped_by' => $validated['mapped_by'] ?? 'manual'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'SNOMED mapping berhasil ditambahkan',
                'data' => $mapping
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambah mapping: ' . $e->getMessage()
            ], 500);
        }
    }
}