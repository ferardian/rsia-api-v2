<?php

namespace App\Http\Controllers\v2;

use App\Helpers\SignHelper;
use App\Http\Controllers\Controller;
use App\Models\RsiaErmBpjs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class BpjsController extends Controller
{
    protected $consId;
    protected $consSecret;
    protected $userKey;
    protected $baseUrl;
    protected $kodeFaskes;
    protected $namaInstansi;
    protected $kodeKemenkes;

    /**
     * Constructor untuk inisialisasi kredensial BPJS dari file konfigurasi dan database setting.
     */
    public function __construct()
    {
        $this->consId = config('services.bpjs.cons_id');
        $this->consSecret = config('services.bpjs.cons_secret');
        $this->userKey = config('services.bpjs.user_key');
        $this->baseUrl = config('services.bpjs.base_url');

        // Ambil data dari tabel setting
        $setting = \DB::table('setting')->first();
        if ($setting) {
            $this->kodeFaskes = $setting->kode_ppk; // BPJS kode faskes dari kode_ppk
            $this->namaInstansi = $setting->nama_instansi; // Nama rumah sakit
            $this->kodeKemenkes = $setting->kode_ppkkemenkes; // Kode kemenkes dari kode_ppkkemenkes
        } else {
            // Fallback ke config jika setting tidak ada
            $this->kodeFaskes = config('services.bpjs.kode_faskes');
            $this->namaInstansi = 'RSIA Aisyiyah Pekajangan'; // Default nama
            $this->kodeKemenkes = '3326051'; // Default kode kemenkes
        }
    }

    /**
     * Cek setting yang digunakan untuk BPJS integration.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSettingInfo()
    {
        $setting = \DB::table('setting')->first();

        return response()->json([
            'message' => 'Informasi Setting untuk BPJS Integration',
            'database_setting' => $setting ? [
                'nama_instansi' => $setting->nama_instansi,
                'kode_ppk_bpjs' => $setting->kode_ppk,
                'kode_ppkkemenkes' => $setting->kode_ppkkemenkes,
                'alamat_instansi' => $setting->alamat_instansi,
                'kabupaten' => $setting->kabupaten,
                'propinsi' => $setting->propinsi
            ] : null,
            'controller_properties' => [
                'nama_instansi' => $this->namaInstansi,
                'kode_faskes_bpjs' => $this->kodeFaskes,
                'kode_kemenkes' => $this->kodeKemenkes,
                'cons_id' => $this->consId,
                'base_url' => $this->baseUrl
            ]
        ]);
    }

    /**
     * Mengenkripsi data menggunakan AES-256-CBC.
     *
     * @param  string $data Data yang akan dienkripsi.
     * @return string Data yang sudah dienkripsi.
     */
    private function encryptDataCorrect($data)
    {
        // Metode enkripsi yang benar sesuai contoh BPJS
        $keyRaw = $this->consId . $this->consSecret . $this->kodeFaskes;

        \Log::info('Correct Encryption Process:');
        \Log::info('- Key Raw: ' . $this->consId . '[SECRET]' . $this->kodeFaskes);
        \Log::info('- Key Raw length: ' . strlen($keyRaw));

        // Generate key dari hash
        $key = hex2bin(hash('sha256', $keyRaw));
        \Log::info('- Key (hex2bin) length: ' . strlen($key));

        // Generate IV (first 16 bytes from key)
        $iv = substr($key, 0, 16);
        \Log::info('- IV length: ' . strlen($iv));
        \Log::info('- Input data length: ' . strlen($data));

        // Encrypt dengan AES-256-CBC
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        \Log::info('- Encrypted data length: ' . strlen($encrypted));
        \Log::info('- Encryption result: ' . ($encrypted !== false ? 'Success' : 'Failed'));

        if ($encrypted === false) {
            \Log::error('Encryption failed, error: ' . openssl_error_string());
            throw new \Exception('Encryption failed');
        }

        // Base64 encode hasil enkripsi
        $final = base64_encode($encrypted);
        \Log::info('- Final base64 length: ' . strlen($final));

        return $final;
    }

    /**
     * Mengenkripsi data menggunakan AES-256-CBC tanpa consId (TESTING).
     *
     * @param  string $data Data yang akan dienkripsi.
     * @return string Data yang sudah dienkripsi.
     */
    private function encryptDataWithoutConsId($data)
    {
        // Metode enkripsi tanpa consId untuk testing
        $keyRaw = $this->consSecret . $this->kodeFaskes;

        \Log::info('Testing Encryption Process (Without ConsID):');
        \Log::info('- Key Raw: [SECRET]' . $this->kodeFaskes);
        \Log::info('- Key Raw length: ' . strlen($keyRaw));

        // Generate key dari hash
        $key = hex2bin(hash('sha256', $keyRaw));
        \Log::info('- Key (hex2bin) length: ' . strlen($key));

        // Generate IV (first 16 bytes from key)
        $iv = substr($key, 0, 16);
        \Log::info('- IV length: ' . strlen($iv));
        \Log::info('- Input data length: ' . strlen($data));

        // Encrypt dengan AES-256-CBC
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        \Log::info('- Encrypted data length: ' . strlen($encrypted));
        \Log::info('- Encryption result: ' . ($encrypted !== false ? 'Success' : 'Failed'));

        if ($encrypted === false) {
            \Log::error('Encryption failed, error: ' . openssl_error_string());
            throw new \Exception('Encryption failed');
        }

        // Base64 encode hasil enkripsi
        $final = base64_encode($encrypted);
        \Log::info('- Final base64 length: ' . strlen($final));

        return $final;
    }

    /**
     * Insert Medical Record ke E-Claim BPJS.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function insertMedicalRecord(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'request.noSep' => 'required|string',
            'request.jnsPelayanan' => 'required|string',
            'request.bulan' => 'required|string',
            'request.tahun' => 'nullable|string',
            'request.dataMR' => 'required', // dataMR bisa berupa array atau object
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Debug: Log entire request untuk debugging
        \Log::info('Full request data: ' . json_encode($request->all()));

        // Handle both formats - with or without "request" wrapper
        // Note: $payload is now set from manual JSON parsing above to bypass Laravel's null conversion
        if ($request->has('request') && !isset($payload)) {
            $payload = $request->input('request');
            \Log::info('Using request wrapper (fallback), payload: ' . json_encode($payload));

            // Debug: Log raw dataMR before processing
            if (isset($payload['dataMR'])) {
                $dataMR = $payload['dataMR'];
                if (is_string($dataMR)) {
                    $dataMR = json_decode($dataMR, true);
                }
                \Log::info('=== RAW dataMR ANALYSIS ===');
                \Log::info('dataMR type: ' . gettype($payload['dataMR']));
                \Log::info('dataMR structure: ' . json_encode($dataMR, JSON_PRETTY_PRINT));

                // Find Composition section
                if (isset($dataMR['entry'])) {
                    foreach ($dataMR['entry'] as $index => $entry) {
                        if (isset($entry['resource']['resourceType']) && $entry['resource']['resourceType'] === 'Composition') {
                            \Log::info("COMPOSITION SECTION IN RAW dataMR (Entry $index): " . json_encode($entry['resource']['section'], JSON_PRETTY_PRINT));
                        }
                    }
                }
                \Log::info('=== END RAW dataMR ANALYSIS ===');
            }
        } else {
            $payload = $request->all();
            \Log::info('Using direct request, payload: ' . json_encode($payload));
        }

        // 1. Ambil FHIR Bundle dari request
        if (!isset($payload['dataMR'])) {
            return response()->json([
                'message' => 'dataMR is required in the request',
                'error' => 'Missing dataMR field',
                'debug_info' => [
                    'payload' => $payload,
                    'request_all' => $request->all()
                ]
            ], 400);
        }

        // Debug: Check what we actually received from frontend
        \Log::info('=== FRONTEND PAYLOAD ANALYSIS ===');

        // Get raw request content to see what's actually received
        $rawContent = $request->getContent();
        \Log::info('Raw HTTP request content: ' . $rawContent);

        // Check if raw content has null or empty string
        if (strpos($rawContent, '"text":null') !== false) {
            \Log::error('FOUND NULL IN RAW HTTP REQUEST!');
            \Log::error('Context: ' . substr($rawContent, strpos($rawContent, '"text":null') - 50, 100));
        } elseif (strpos($rawContent, '"text":""') !== false) {
            \Log::info('FOUND EMPTY STRING IN RAW HTTP REQUEST - GOOD!');
        }

        // Parse raw JSON manually to compare with Laravel's parsing
        $manualParse = json_decode($rawContent, true);
        if ($manualParse !== null) {
            \Log::info('Manual JSON parse successful - USING MANUAL PARSE TO BYPASS LARAVEL NULL CONVERSION');

            // USE THE MANUAL PARSE INSTEAD OF LARAVEL'S REQUEST PARSING
            // This prevents Laravel middleware from converting empty strings to null
            $payload = $manualParse['request'];

            // Check procedure notes in manual parse
            if (isset($manualParse['request']['dataMR']['entry'])) {
                foreach ($manualParse['request']['dataMR']['entry'] as $entryIndex => $entry) {
                    if (isset($entry['resource']) && is_array($entry['resource'])) {
                        foreach ($entry['resource'] as $resourceIndex => $resource) {
                            if (isset($resource['resourceType']) && $resource['resourceType'] === 'Procedure') {
                                if (isset($resource['note'])) {
                                    foreach ($resource['note'] as $noteIndex => $note) {
                                        $textVal = $note['text'] ?? 'MISSING';
                                        $textType = gettype($textVal);
                                        \Log::info("MANUAL PARSE - Procedure $entryIndex/$resourceIndex Note $noteIndex: text='$textVal' (type: $textType)");
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            \Log::error('Manual JSON parse failed, falling back to Laravel request parsing');
            // Fallback to Laravel's parsing if manual parse fails
            $payload = $request->input('request');
        }

        \Log::info('Raw payload structure: ' . json_encode($payload, JSON_PRETTY_PRINT));

        // Check specifically the dataMR field
        if (isset($payload['dataMR'])) {
            \Log::info('dataMR type: ' . gettype($payload['dataMR']));
            \Log::info('dataMR content: ' . json_encode($payload['dataMR'], JSON_PRETTY_PRINT));

            // Look for any null text values in the payload
            $payloadJson = json_encode($payload['dataMR']);
            if (strpos($payloadJson, '"text":null') !== false) {
                \Log::error('FOUND NULL IN PAYLOAD: ' . substr($payloadJson, strpos($payloadJson, '"text":null') - 20, 100));
            } else {
                \Log::info('No null text found in payload');
            }
        }

        $fhirBundle = $payload['dataMR'];
        \Log::info('FHIR Bundle found: ' . (is_array($fhirBundle) ? 'Array with ' . count($fhirBundle) . ' entries' : 'Not an array'));

        // Debug: Log FHIR Bundle info
        \Log::info('FHIR Bundle received: ' . json_encode($fhirBundle));

        // Simpan bundle ERM sebelum enkripsi ke database
        try {
            \Log::info('Menyimpan bundle ERM ke database untuk SEP: ' . $payload['noSep']);

            $requestMetadata = [
                'jnsPelayanan' => $payload['jnsPelayanan'],
                'bulan' => $payload['bulan'],
                'tahun' => $payload['tahun'],
                'bundle_entries_count' => isset($fhirBundle['entry']) ? count($fhirBundle['entry']) : 0,
                'bundle_type' => $fhirBundle['resourceType'] ?? 'unknown',
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ];

            RsiaErmBpjs::saveErmRequest($payload['noSep'], $fhirBundle, $requestMetadata);
            \Log::info('Bundle ERM berhasil disimpan ke database');
        } catch (\Exception $e) {
            \Log::error('Gagal menyimpan bundle ERM ke database: ' . $e->getMessage());
            // Tetap lanjut proses meskipun gagal menyimpan ke database
        }

        // Debug: Check Procedure note fields at initial reception - DETAILED ANALYSIS
        \Log::info('=== DETAILED PROCEDURE NOTE ANALYSIS ===');
        if (isset($fhirBundle['entry']) && is_array($fhirBundle['entry'])) {
            foreach ($fhirBundle['entry'] as $entryIndex => $entry) {
                // Handle array-wrapped resources (like Procedures)
                if (is_array($entry['resource'])) {
                    foreach ($entry['resource'] as $resourceIndex => $resource) {
                        // Convert resource to array if it's an object
                        if (is_object($resource)) {
                            $resource = (array)$resource;
                        }
                        $resourceType = $resource['resourceType'] ?? '';
                        if ($resourceType === 'Procedure') {
                            \Log::info("PROCEDURE FOUND (Array-wrapped): Entry $entryIndex, Resource $resourceIndex");
                            \Log::info("Procedure data: " . json_encode($resource));

                            if (isset($resource['note']) && is_array($resource['note'])) {
                                foreach ($resource['note'] as $noteIndex => $noteItem) {
                                    if (is_object($noteItem)) {
                                        $noteItem = (array)$noteItem;
                                    }
                                    $textValue = $noteItem['text'] ?? 'MISSING';
                                    $isNull = is_null($noteItem['text']) ? 'NULL' : 'NOT NULL';
                                    $isEmpty = $noteItem['text'] === '' ? 'EMPTY STRING' : 'NOT EMPTY';
                                    $type = gettype($noteItem['text']);

                                    \Log::info("NOTE ANALYSIS (Array-wrapped): Entry $entryIndex, Resource $resourceIndex, Note $noteIndex");
                                    \Log::info("  - text value: '$textValue'");
                                    \Log::info("  - is_null: $isNull");
                                    \Log::info("  - is_empty: $isEmpty");
                                    \Log::info("  - type: $type");
                                    \Log::info("  - raw noteItem: " . json_encode($noteItem));
                                }
                            } else {
                                \Log::info("NO NOTE FIELD FOUND in Procedure (Array-wrapped): Entry $entryIndex, Resource $resourceIndex");
                            }
                        }
                    }
                }
                // Handle single resources
                else {
                    $resource = $entry['resource'];
                    // Convert resource to array if it's an object
                    if (is_object($resource)) {
                        $resource = (array)$resource;
                    }
                    $resourceType = $resource['resourceType'] ?? '';
                    if ($resourceType === 'Procedure') {
                        \Log::info("PROCEDURE FOUND (Single): Entry $entryIndex");
                        \Log::info("Procedure data: " . json_encode($resource));

                        if (isset($resource['note']) && is_array($resource['note'])) {
                            foreach ($resource['note'] as $noteIndex => $noteItem) {
                                if (is_object($noteItem)) {
                                    $noteItem = (array)$noteItem;
                                }
                                $textValue = $noteItem['text'] ?? 'MISSING';
                                $isNull = is_null($noteItem['text']) ? 'NULL' : 'NOT NULL';
                                $isEmpty = $noteItem['text'] === '' ? 'EMPTY STRING' : 'NOT EMPTY';
                                $type = gettype($noteItem['text']);

                                \Log::info("NOTE ANALYSIS (Single): Entry $entryIndex, Note $noteIndex");
                                \Log::info("  - text value: '$textValue'");
                                \Log::info("  - is_null: $isNull");
                                \Log::info("  - is_empty: $isEmpty");
                                \Log::info("  - type: $type");
                                \Log::info("  - raw noteItem: " . json_encode($noteItem));
                            }
                        } else {
                            \Log::info("NO NOTE FIELD FOUND in Procedure (Single): Entry $entryIndex");
                        }
                    }
                }
            }
        } else {
            \Log::info("NO ENTRIES FOUND IN FHIR BUNDLE");
        }

        // Debug: Deep JSON structure analysis to find all JObject vs JArray issues
        if (isset($fhirBundle['entry'])) {
            \Log::info('=== DEEP BUNDLE STRUCTURE ANALYSIS ===');
            \Log::info('Entry count: ' . count($fhirBundle['entry']));

            foreach ($fhirBundle['entry'] as $index => $entry) {
                if (!isset($entry['resource'])) {
                    \Log::error("Entry $index: Missing 'resource' field");
                    continue;
                }

                $resource = $entry['resource'];
                $resourceType = $resource['resourceType'] ?? 'unknown';
                \Log::info("=== Entry $index: $resourceType ===");

                // Recursively check all nested fields for array/object issues
                $this->analyzeResourceStructure($resource, $resourceType, 0);

                \Log::info("--- End Entry $index ---\n");
            }
            \Log::info('=== END DEEP BUNDLE ANALYSIS ===');
        }

        // Validasi required fields dari payload
        $requiredFields = ['noSep', 'jnsPelayanan', 'bulan', 'tahun'];
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                return response()->json([
                    'message' => "Missing required field: {$field}",
                    'error' => "Field {$field} is required",
                    'debug_info' => [
                        'payload' => $payload,
                        'available_fields' => array_keys($payload)
                    ]
                ], 400);
            }
        }

        // 2. Convert FHIR Bundle ke JSON
        $fhirJson = json_encode($fhirBundle, JSON_UNESCAPED_SLASHES);
        \Log::info('FHIR JSON length: ' . strlen($fhirJson));
        \Log::info('FHIR JSON (first 200 chars): ' . substr($fhirJson, 0, 200) . '...');

        // 3. Compress dengan gzencode (bukan gzcompress)
        $compressedData = gzencode($fhirJson, 9);
        \Log::info('Compressed data length (gzencode): ' . strlen($compressedData));

        // 4. Base64 encode compressed data
        $compressedBase64 = base64_encode($compressedData);
        \Log::info('Compressed base64 length: ' . strlen($compressedBase64));

        // 5. Enkripsi dengan metode yang benar
        $finalData = $this->encryptDataCorrect($compressedBase64);
        \Log::info('Final encrypted data length: ' . strlen($finalData));
        \Log::info('Final encrypted data (first 100 chars): ' . substr($finalData, 0, 100) . '...');

        // 5. Ambil jenis pelayanan yang benar dari bridging_sep
        $sepData = \DB::table('bridging_sep')
            ->where('no_sep', $payload['noSep'])
            ->first();

        // Debug log untuk mengetahui sumber data
        \Log::info('Debug Jenis Pelayanan:');
        \Log::info('Payload jnsPelayanan: ' . $payload['jnsPelayanan']);
        \Log::info('Bridging SEP data found: ' . ($sepData ? 'YES' : 'NO'));

        if ($sepData) {
            \Log::info('Bridging SEP jnspelayanan: ' . $sepData->jnspelayanan);
            \Log::info('Bridging SEP jnspelayanan type: ' . gettype($sepData->jnspelayanan));
        }

        $jnsPelayanan = $payload['jnsPelayanan']; // default dari payload

        // Prioritaskan data yang lebih akurat: ERM bundle payload
        // Fallback ke bridging_sep jika tidak sesuai
        if ($payload['jnsPelayanan'] === '1' || $payload['jnsPelayanan'] === 1) {
            // Payload mengirim rawat inap, gunakan 1
            $jnsPelayanan = '1';
            \Log::info('Using Rawat Inap (1) from payload - ERM bundle indicates inpatient');
        } elseif ($payload['jnsPelayanan'] === '2' || $payload['jnsPelayanan'] === 2) {
            // Payload mengirim rawat jalan, gunakan 2
            $jnsPelayanan = '2';
            \Log::info('Using Rawat Jalan (2) from payload - ERM bundle indicates outpatient');
        } elseif ($sepData && $sepData->jnspelayanan) {
            // Fallback ke bridging_sep jika payload tidak jelas
            $jnsPelayanan = strtolower($sepData->jnspelayanan) === 'rawat inap' ? '1' : '2';
            \Log::info('Using fallback from bridging_sep: ' . $sepData->jnspelayanan . ' -> BPJS format: ' . $jnsPelayanan);
        } else {
            \Log::info('Using default fallback jnsPelayanan from payload: ' . $jnsPelayanan);
        }

        // 6. Buat final payload sesuai format BPJS
        $finalPayload = [
            "request" => [
                "noSep" => $payload['noSep'],
                "jnsPelayanan" => $jnsPelayanan,
                "bulan" => $payload['bulan'],
                "tahun" => $payload['tahun'],
                "dataMR" => $finalData // Data FHIR yang sudah di-compress dan di-encrypt
            ]
        ];
        
        // 4. Generate Signature - Follow BPJS example exactly
        date_default_timezone_set('UTC');
        $timestamp = strval(time() - strtotime('1970-01-01 00:00:00'));

        // BPJS signature algorithm: HMAC-SHA256(consId + "&" + timestamp, consSecret)
        $stringToSign = $this->consId . '&' . $timestamp;
        \Log::info('String to sign: ' . $stringToSign);

        // Create HMAC-SHA256 signature (raw binary)
        $hmacSignature = hash_hmac('sha256', $stringToSign, $this->consSecret, true);

        // Base64 encode the signature (sesuai contoh BPJS)
        $signature = base64_encode($hmacSignature);

        $signatureData = [
            'timestamp' => $timestamp,
            'signature' => $signature
        ];

        // Debug: Log signature details and configuration
        \Log::info('BPJS Configuration & Signature Debug:');
        \Log::info('Environment: ' . config('app.env'));
        \Log::info('Base URL: ' . $this->baseUrl);
        \Log::info('User Key: ' . $this->userKey);
        \Log::info('Cons ID: ' . $this->consId);
        \Log::info('Cons Secret length: ' . strlen($this->consSecret));
        \Log::info('Kode Faskes: ' . $this->kodeFaskes);
        \Log::info('Timestamp: ' . $timestamp);
        \Log::info('Current Time: ' . time());
        \Log::info('String to Sign: ' . $stringToSign);
        \Log::info('HMAC Signature (base64): ' . $signature);
        \Log::info('Signature Length: ' . strlen($signature));
        \Log::info('Formatted Timestamp (Y-m-d H:i:s): ' . date('Y-m-d H:i:s', (int)$timestamp));

        // 6. Siapkan Headers sesuai dokumentasi BPJS
        $headers = [
            'X-cons-id'     => $this->consId,
            'X-timestamp'   => $signatureData['timestamp'],
            'X-signature'   => $signatureData['signature'],
            'user_key'      => $this->userKey,
            'Content-Type'  => 'text/plain',
        ];

        // 6. Kirim request ke BPJS
        // Use correct BPJS e-claim API endpoint with full path
        $serviceUrl = $this->baseUrl . '/erekammedis_dev/eclaim/rekammedis/insert';
        // $serviceUrl = $this->baseUrl . '/medicalrecord/eclaim/rekammedis/insert';

        // Debug: Log service URL attempts
        \Log::info('BPJS Service URL: ' . $serviceUrl);

        // Debug: Log final payload info
        \Log::info('Final payload dataMR length: ' . strlen($finalPayload['request']['dataMR']));
        \Log::info('Final payload keys: ' . json_encode(array_keys($finalPayload)));
        \Log::info('Request payload keys: ' . json_encode(array_keys($finalPayload['request'])));

        // Fix FHIR Bundle structure to prevent JObject to JArray casting issues
        \Log::info('=== FIXING FHIR BUNDLE STRUCTURE ===');
        if (isset($fhirBundle['entry'])) {
            \Log::info('Total entries before filtering: ' . count($fhirBundle['entry']));

            // Filter out entries with missing or invalid resource fields
            $validEntries = [];
            foreach ($fhirBundle['entry'] as $index => $entry) {
                if (!isset($entry['resource'])) {
                    \Log::warning("Entry $index: Missing 'resource' field - filtering out");
                    continue;
                }

                $resource = $entry['resource'];
                if (is_null($resource) || (is_array($resource) && empty($resource))) {
                    \Log::warning("Entry $index: Resource is null or empty - filtering out");
                    continue;
                }

                // Only add entries with valid resources
                $validEntries[] = $entry;
            }

            // Replace the original entries with filtered ones
            $fhirBundle['entry'] = $validEntries;
            \Log::info('Total entries after filtering: ' . count($fhirBundle['entry']));

            // Debug: Log the structure before and after fixes
            \Log::info('=== DETAILED STRUCTURE ANALYSIS ===');
            \Log::info('FHIR Bundle structure before fixes: ' . json_encode($fhirBundle, JSON_PRETTY_PRINT));

            foreach ($fhirBundle['entry'] as $index => $entry) {
                $resource = $entry['resource'];

                // DISABLED: Convert resource to array for consistent handling
                // We will process everything through applySelectiveStructureFixes to avoid conflicts
                /*
                if (is_object($resource)) {
                    $resource = (array)$resource;
                    $fhirBundle['entry'][$index]['resource'] = $resource;
                }

                $resourceType = $resource['resourceType'] ?? 'unknown';

                // Fix common JObject to JArray issues by converting objects to arrays where needed
                switch ($resourceType) {
                    case 'Composition':
                        if (isset($resource['section']) && !is_array($resource['section'])) {
                            // Convert Composition.section from object to array
                            $sections = (array)$resource['section'];
                            $indexedSections = array_values($sections);
                            $fhirBundle['entry'][$index]['resource']['section'] = $indexedSections;
                            \Log::info("Entry $index: Fixed Composition.section structure - converted object to array with " . count($indexedSections) . " items");
                        }
                        break;

                    case 'Encounter':
                        if (isset($resource['diagnosis']) && !is_array($resource['diagnosis'])) {
                            // Convert Encounter.diagnosis from object to array
                            $diagnosis = (array)$resource['diagnosis'];
                            $indexedDiagnosis = array_values($diagnosis);
                            $fhirBundle['entry'][$index]['resource']['diagnosis'] = $indexedDiagnosis;
                            \Log::info("Entry $index: Fixed Encounter.diagnosis structure - converted object to array with " . count($indexedDiagnosis) . " items");
                        }
                        if (isset($resource['participant']) && !is_array($resource['participant'])) {
                            $participants = (array)$resource['participant'];
                            $indexedParticipants = array_values($participants);
                            $fhirBundle['entry'][$index]['resource']['participant'] = $indexedParticipants;
                            \Log::info("Entry $index: Fixed Encounter.participant structure - converted object to array with " . count($indexedParticipants) . " items");
                        }
                        if (isset($resource['type']) && !is_array($resource['type'])) {
                            $types = (array)$resource['type'];
                            $indexedTypes = array_values($types);
                            $fhirBundle['entry'][$index]['resource']['type'] = $indexedTypes;
                            \Log::info("Entry $index: Fixed Encounter.type structure - converted object to array with " . count($indexedTypes) . " items");
                        }
                        break;

                    case 'Patient':
                        if (isset($resource['name']) && !is_array($resource['name'])) {
                            $names = (array)$resource['name'];
                            $indexedNames = array_values($names);
                            $fhirBundle['entry'][$index]['resource']['name'] = $indexedNames;
                            \Log::info("Entry $index: Fixed Patient.name structure - converted object to array with " . count($indexedNames) . " items");
                        }
                        if (isset($resource['identifier']) && !is_array($resource['identifier'])) {
                            $identifiers = (array)$resource['identifier'];
                            $indexedIdentifiers = array_values($identifiers);
                            $fhirBundle['entry'][$index]['resource']['identifier'] = $indexedIdentifiers;
                            \Log::info("Entry $index: Fixed Patient.identifier structure - converted object to array with " . count($indexedIdentifiers) . " items");
                        }
                        if (isset($resource['telecom']) && !is_array($resource['telecom'])) {
                            $telecoms = (array)$resource['telecom'];
                            $indexedTelecoms = array_values($telecoms);
                            $fhirBundle['entry'][$index]['resource']['telecom'] = $indexedTelecoms;
                            \Log::info("Entry $index: Fixed Patient.telecom structure - converted object to array with " . count($indexedTelecoms) . " items");
                        }
                        if (isset($resource['address']) && !is_array($resource['address'])) {
                            $addresses = (array)$resource['address'];
                            $indexedAddresses = array_values($addresses);
                            $fhirBundle['entry'][$index]['resource']['address'] = $indexedAddresses;
                            \Log::info("Entry $index: Fixed Patient.address structure - converted object to array with " . count($indexedAddresses) . " items");
                        }
                        break;

                    case 'Practitioner':
                        if (isset($resource['name']) && !is_array($resource['name'])) {
                            $names = (array)$resource['name'];
                            $indexedNames = array_values($names);
                            $fhirBundle['entry'][$index]['resource']['name'] = $indexedNames;
                            \Log::info("Entry $index: Fixed Practitioner.name structure - converted object to array with " . count($indexedNames) . " items");
                        }
                        if (isset($resource['qualification']) && !is_array($resource['qualification'])) {
                            $qualifications = (array)$resource['qualification'];
                            $indexedQualifications = array_values($qualifications);
                            $fhirBundle['entry'][$index]['resource']['qualification'] = $indexedQualifications;
                            \Log::info("Entry $index: Fixed Practitioner.qualification structure - converted object to array with " . count($indexedQualifications) . " items");
                        }
                        break;

                    case 'Condition':
                        if (isset($resource['category']) && !is_array($resource['category'])) {
                            $categories = (array)$resource['category'];
                            $indexedCategories = array_values($categories);
                            $fhirBundle['entry'][$index]['resource']['category'] = $indexedCategories;
                            \Log::info("Entry $index: Fixed Condition.category structure - converted object to array with " . count($indexedCategories) . " items");
                        }
                        break;

                    case 'MedicationRequest':
                        if (isset($resource['dosageInstruction']) && !is_array($resource['dosageInstruction'])) {
                            $dosageInstructions = (array)$resource['dosageInstruction'];
                            $indexedDosageInstructions = array_values($dosageInstructions);
                            $fhirBundle['entry'][$index]['resource']['dosageInstruction'] = $indexedDosageInstructions;
                            \Log::info("Entry $index: Fixed MedicationRequest.dosageInstruction structure - converted object to array with " . count($indexedDosageInstructions) . " items");
                        }
                        break;

                    case 'Procedure':
                        if (isset($resource['performer']) && !is_array($resource['performer'])) {
                            $performers = (array)$resource['performer'];
                            $indexedPerformers = array_values($performers);
                            $fhirBundle['entry'][$index]['resource']['performer'] = $indexedPerformers;
                            \Log::info("Entry $index: Fixed Procedure.performer structure - converted object to array with " . count($indexedPerformers) . " items");
                        }
                        if (isset($resource['bodySite']) && !is_array($resource['bodySite'])) {
                            $bodySites = (array)$resource['bodySite'];
                            $indexedBodySites = array_values($bodySites);
                            $fhirBundle['entry'][$index]['resource']['bodySite'] = $indexedBodySites;
                            \Log::info("Entry $index: Fixed Procedure.bodySite structure - converted object to array with " . count($indexedBodySites) . " items");
                        }
                        break;

                    case 'Organization':
                        if (isset($resource['type']) && !is_array($resource['type'])) {
                            $types = (array)$resource['type'];
                            $indexedTypes = array_values($types);
                            $fhirBundle['entry'][$index]['resource']['type'] = $indexedTypes;
                            \Log::info("Entry $index: Fixed Organization.type structure - converted object to array with " . count($indexedTypes) . " items");
                        }
                        if (isset($resource['telecom']) && !is_array($resource['telecom'])) {
                            $telecoms = (array)$resource['telecom'];
                            $indexedTelecoms = array_values($telecoms);
                            $fhirBundle['entry'][$index]['resource']['telecom'] = $indexedTelecoms;
                            \Log::info("Entry $index: Fixed Organization.telecom structure - converted object to array with " . count($indexedTelecoms) . " items");
                        }
                        break;

                    case 'Bundle':
                        if (isset($resource['entry']) && !is_array($resource['entry'])) {
                            $entries = (array)$resource['entry'];
                            $indexedEntries = array_values($entries);
                            $fhirBundle['entry'][$index]['resource']['entry'] = $indexedEntries;
                            \Log::info("Entry $index: Fixed Bundle.entry structure - converted object to array with " . count($indexedEntries) . " items");
                        }
                        break;
                }
                */

                // TEMPORARILY DISABLED: Recursively fix all nested array structures that should be arrays
                // $this->fixNestedArrays($fhirBundle['entry'][$index]['resource'], $resourceType);
            }

            // Apply fixes only for single resources (not array-wrapped ones)
            // Convert sparse keys to consecutive numeric keys for BPJS compatibility
            \Log::info('About to call convertCompositionSectionsToConsecutiveKeys');
            $this->convertCompositionSectionsToConsecutiveKeys($fhirBundle);
            \Log::info('Finished convertCompositionSectionsToConsecutiveKeys');

            \Log::info('=== BEFORE APPLYING SELECTIVE STRUCTURE FIXES ===');
            // Log Composition section structure before fixes
            foreach ($fhirBundle['entry'] as $index => $entry) {
                if (isset($entry['resource']['resourceType']) && $entry['resource']['resourceType'] === 'Composition') {
                    \Log::info("Entry $index Composition section BEFORE: " . json_encode($entry['resource']['section'], JSON_PRETTY_PRINT));
                }
            }

            $this->applySelectiveStructureFixes($fhirBundle);

            \Log::info('=== AFTER APPLYING SELECTIVE STRUCTURE FIXES ===');
            // Log Composition section structure after fixes
            foreach ($fhirBundle['entry'] as $index => $entry) {
                if (isset($entry['resource']['resourceType']) && $entry['resource']['resourceType'] === 'Composition') {
                    \Log::info("Entry $index Composition section AFTER: " . json_encode($entry['resource']['section'], JSON_PRETTY_PRINT));
                }
            }

            \Log::info('FHIR Bundle structure after all fixes: ' . json_encode($fhirBundle, JSON_PRETTY_PRINT));

            // FINAL COMPREHENSIVE FIX: Ensure Procedure note text is never null
            \Log::info('=== APPLYING FINAL PROCEDURE NOTE FIX ===');
            if (isset($fhirBundle['entry']) && is_array($fhirBundle['entry'])) {
                foreach ($fhirBundle['entry'] as $entryIndex => &$entry) {
                    // Handle array-wrapped resources (like Procedures)
                    if (is_array($entry['resource'])) {
                        foreach ($entry['resource'] as &$resource) {
                            // Convert resource to array if it's an object
                            if (is_object($resource)) {
                                $resource = (array)$resource;
                            }
                            $resourceType = $resource['resourceType'] ?? '';
                            if ($resourceType === 'Procedure' && isset($resource['note']) && is_array($resource['note'])) {
                                foreach ($resource['note'] as &$noteItem) {
                                    if (is_object($noteItem)) {
                                        $noteItem = (array)$noteItem;
                                    }
                                    if (isset($noteItem['text']) && is_null($noteItem['text'])) {
                                        $noteItem['text'] = '';
                                        \Log::info("FINAL FIX (Array-wrapped): Entry $entryIndex - Converted Procedure note text from null to empty string");
                                    }
                                }
                            }
                        }
                    }
                    // Handle single resources
                    else {
                        $resource = &$entry['resource'];
                        // Convert resource to array if it's an object
                        if (is_object($resource)) {
                            $resource = (array)$resource;
                        }
                        $resourceType = $resource['resourceType'] ?? '';
                        if ($resourceType === 'Procedure' && isset($resource['note']) && is_array($resource['note'])) {
                            foreach ($resource['note'] as &$noteItem) {
                                if (is_object($noteItem)) {
                                    $noteItem = (array)$noteItem;
                                }
                                if (isset($noteItem['text']) && is_null($noteItem['text'])) {
                                    $noteItem['text'] = '';
                                    \Log::info("FINAL FIX (Single): Entry $entryIndex - Converted Procedure note text from null to empty string");
                                }
                            }
                        }
                    }
                }
            }
            \Log::info('FHIR Bundle structure after final Procedure note fix: ' . json_encode($fhirBundle, JSON_PRETTY_PRINT));
        }
        \Log::info('=== END FHIR BUNDLE STRUCTURE FIX ===');

        try {
            // Debug: Log request details before sending
            \Log::info('BPJS Request Details:');
            \Log::info('Service URL: ' . $serviceUrl);
            \Log::info('Headers: ' . json_encode($headers));
            \Log::info('Payload: ' . json_encode($finalPayload));
            \Log::info('Cons ID: ' . $this->consId);
            \Log::info('User Key: ' . $this->userKey);
            \Log::info('Timestamp: ' . $signatureData['timestamp']);

            // PRE-ENCODING FIX: Fix null text values in the PHP array before JSON encoding
            \Log::info('=== APPLYING PRE-ENCODING PHP ARRAY FIX ===');

            // Debug: Check if there are any null text values before the fix
            $this->debugNullTextValues($finalPayload, 'BEFORE');

            $this->fixNullTextValuesInArray($finalPayload);

            // Debug: Check if there are any null text values after the fix
            $this->debugNullTextValues($finalPayload, 'AFTER');

            // Debug: Log exact JSON being sent
            $requestJson = json_encode($finalPayload, JSON_UNESCAPED_SLASHES);

            // CRITICAL DEBUG: Show the exact JSON being sent to BPJS
            \Log::info('=== CRITICAL: JSON BEING SENT TO BPJS ===');
            if (strpos($requestJson, '"text":null') !== false) {
                \Log::error('FOUND NULL IN JSON: ' . substr($requestJson, strpos($requestJson, '"text":null') - 20, 100));
            } else {
                \Log::info('NO NULL FOUND IN JSON around text fields');
            }
            \Log::info('JSON Preview (first 1000 chars): ' . substr($requestJson, 0, 1000));

            // FINAL JSON-LEVEL FIX: Replace any remaining null text values in Procedure note with empty strings
            \Log::info('=== APPLYING FINAL JSON-LEVEL PROCEDURE NOTE FIX ===');
            $originalJson = $requestJson;

            // Multiple approaches to catch and replace null text values
            // 1. Simple string replacement for the exact pattern
            $requestJson = str_replace('"text":null', '"text":""', $requestJson);
            $requestJson = str_replace('"text": null', '"text":""', $requestJson);

            // 2. More aggressive approach - replace ALL null values in text contexts
            $requestJson = preg_replace('/"text":\s*null\b/', '"text":""', $requestJson);

            // 3. Handle multi-line formatted JSON with proper word boundaries
            $requestJson = preg_replace('/"text"\s*:\s*null\b/', '"text":""', $requestJson);

            // 4. Handle any null value that comes after "text": with any whitespace
            $requestJson = preg_replace('/"text"[^:]*:\s*null/', '"text":""', $requestJson);

            // 5. Comprehensive approach - replace ALL null values in the entire JSON that are assigned to "text" keys
            preg_match_all('/"text"[^:]*:\s*null/', $requestJson, $matches);
            if (!empty($matches[0])) {
                \Log::info("Found text:null patterns to replace: " . json_encode($matches[0]));
                foreach ($matches[0] as $match) {
                    $requestJson = str_replace($match, '"text":""', $requestJson);
                }
            }

            // 6. Last resort - replace ALL remaining null values in note contexts
            $requestJson = preg_replace('/("note"\s*:\s*\[[^\]]*{[^}]*"text"\s*:\s*)null(\s*[^}]*}[^]]*\])/', '$1""$2', $requestJson);

            // 7. Final catch-all - replace any remaining null values in the JSON
            // but be very careful to only replace null values that appear to be in valid JSON key:value pairs
            $requestJson = preg_replace('/("[^"]+"\s*:\s*)null(?=\s*[,\]\}])/', '$1""', $requestJson);

            if ($originalJson !== $requestJson) {
                \Log::info("JSON-LEVEL FIX: Applied procedure note null to empty string conversion");
                \Log::info("Original snippet: " . substr($originalJson, strpos($originalJson, '"note"'), 150));
                \Log::info("Fixed snippet: " . substr($requestJson, strpos($requestJson, '"note"'), 150));

                // Check if any null values still remain
                if (strpos($requestJson, '"text":null') !== false || strpos($requestJson, '"text": null') !== false) {
                    \Log::error("JSON-LEVEL FIX FAILED: Still found null text values after replacement!");
                    \Log::error("Remaining null pattern: " . substr($requestJson, strpos($requestJson, '"text":'), 50));
                } else {
                    \Log::info("JSON-LEVEL FIX SUCCESS: No null text values remaining");
                }
            } else {
                \Log::info("JSON-LEVEL FIX: No procedure note null values found to fix");

                // Debug: Check what the actual pattern looks like
                if (strpos($requestJson, '"note"') !== false) {
                    $noteSection = substr($requestJson, strpos($requestJson, '"note"'), 300);
                    \Log::info("Actual note section found: " . $noteSection);
                }

                // Additional debug: Look for any 'null' patterns in the JSON
                if (strpos($requestJson, 'null') !== false) {
                    \Log::info("Found 'null' patterns in JSON - investigating contexts:");
                    $contextStart = max(0, strpos($requestJson, 'null') - 50);
                    $contextEnd = min(strlen($requestJson), strpos($requestJson, 'null') + 50);
                    \Log::info("Null context: " . substr($requestJson, $contextStart, $contextEnd - $contextStart));
                }

                // Debug: Look for 'text' patterns specifically
                if (strpos($requestJson, '"text"') !== false) {
                    \Log::info("Found 'text' patterns in JSON - investigating all contexts:");
                    $pos = 0;
                    while (($pos = strpos($requestJson, '"text"', $pos)) !== false) {
                        $contextStart = max(0, $pos - 30);
                        $contextEnd = min(strlen($requestJson), $pos + 50);
                        \Log::info("Text context " . $pos . ": " . substr($requestJson, $contextStart, $contextEnd - $contextStart));
                        $pos++;
                    }
                }
            }

            \Log::info('=== SENDING REQUEST TO BPJS ===');
            \Log::info('Request JSON: ' . $requestJson);
            \Log::info('Request JSON length: ' . strlen($requestJson));
            \Log::info('Content-Type: text/plain (as per BPJS documentation)');
            \Log::info('=====================================');

            // Send to BPJS API as plain text (as per BPJS documentation)
            $response = Http::withHeaders($headers)->withBody($requestJson, 'text/plain')->post($serviceUrl);

            // Debug: Log response details
            \Log::info('BPJS Response Status: ' . $response->status());
            \Log::info('BPJS Response Headers: ' . json_encode($response->headers()));
            \Log::info('BPJS Response Body: ' . $response->body());

            // Handle 401 error - retry with fresh timestamp
            if ($response->status() === 401) {
                \Log::info('Got 401, retrying with fresh timestamp...');

                // Generate fresh timestamp and signature with same algorithm
                $freshTimestamp = strval(time() - strtotime('1970-01-01 00:00:00'));
                $freshStringToSign = $this->consId . '&' . $freshTimestamp;
                $freshHmacSignature = hash_hmac('sha256', $freshStringToSign, $this->consSecret, true);
                $freshSignature = base64_encode($freshHmacSignature);

                $freshHeaders = [
                    'X-cons-id'     => $this->consId,
                    'X-timestamp'   => $freshTimestamp,
                    'X-signature'   => $freshSignature,
                    'user_key'      => $this->userKey,
                    'Content-Type'  => 'text/plain',
                ];

                \Log::info('Retry String to sign: ' . $freshStringToSign);
                \Log::info('Retry Signature (base64): ' . $freshSignature);
                \Log::info('Retry with timestamp: ' . $freshTimestamp);

                $response = Http::withHeaders($freshHeaders)->withBody($requestJson, 'text/plain')->post($serviceUrl);

                \Log::info('Retry Response Status: ' . $response->status());
                \Log::info('Retry Response Body: ' . $response->body());

                // Simpan retry response dari BPJS ke database
                try {
                    \Log::info('Menyimpan retry response BPJS ke database untuk SEP: ' . $payload['noSep']);

                    $retryResponseMetadata = [
                        'http_status' => $response->status(),
                        'response_headers' => $response->headers(),
                        'request_metadata' => [
                            'service_url' => $serviceUrl,
                            'timestamp' => $freshTimestamp,
                            'payload_size' => strlen($requestJson),
                            'is_retry' => true,
                        ],
                        'processing_time' => microtime(true) - LARAVEL_START,
                    ];

                    RsiaErmBpjs::saveErmResponse($payload['noSep'], $response->json(), $retryResponseMetadata);
                    \Log::info('Retry response BPJS berhasil disimpan ke database');
                } catch (\Exception $e) {
                    \Log::error('Gagal menyimpan retry response BPJS ke database: ' . $e->getMessage());
                    // Tetap lanjut proses meskipun gagal menyimpan ke database
                }
            }

            // Return appropriate response based on BPJS API response
            $bpjsResponse = $response->json();

            // Simpan response dari BPJS ke database
            try {
                \Log::info('Menyimpan response BPJS ke database untuk SEP: ' . $payload['noSep']);

                $responseMetadata = [
                    'http_status' => $response->status(),
                    'response_headers' => $response->headers(),
                    'request_metadata' => [
                        'service_url' => $serviceUrl,
                        'timestamp' => $signatureData['timestamp'],
                        'payload_size' => strlen($requestJson),
                    ],
                    'processing_time' => microtime(true) - LARAVEL_START,
                ];

                RsiaErmBpjs::saveErmResponse($payload['noSep'], $bpjsResponse, $responseMetadata);
                \Log::info('Response BPJS berhasil disimpan ke database');
            } catch (\Exception $e) {
                \Log::error('Gagal menyimpan response BPJS ke database: ' . $e->getMessage());
                // Tetap lanjut proses meskipun gagal menyimpan ke database
            }

            if ($response->successful()) {
                return response()->json([
                    'message' => 'Data rekam medis berhasil dikirim ke BPJS',
                    'success' => true,
                    'metadata' => [
                        'no_sep' => $payload['noSep'],
                        'jenis_pelayanan' => $payload['jnsPelayanan'],
                        'bulan' => $payload['bulan'],
                        'tahun' => $payload['tahun'],
                        'rumah_sakit' => [
                            'nama_instansi' => $this->namaInstansi,
                            'kode_faskes_bpjs' => $this->kodeFaskes,
                            'kode_kemenkes' => $this->kodeKemenkes
                        ]
                    ],
                    'bpjs_response' => $bpjsResponse
                ], 200);
            } else {
                // Log BPJS error response
                \Log::error('BPJS API Error Response: ' . $response->body());
                \Log::error('BPJS API Status Code: ' . $response->status());
                \Log::error('BPJS API Headers: ' . json_encode($response->headers()));

                // If first attempt fails, try with base64 encoding
                if ($response->status() === 401 || $response->status() === 400) {
                    \Log::info('Trying with base64 encoded data...');

                    // Try with base64 encoding and URL encoding
                    $base64Data = urlencode(base64_encode($finalData));
                    $altPayload = [
                        "noSep" => $payload['noSep'],
                        "jnsPelayanan" => $payload['jnsPelayanan'],
                        "bulan" => $payload['bulan'],
                        "tahun" => $payload['tahun'],
                        "dataMR" => $base64Data,
                    ];

                    \Log::info('Base64 encoded dataMR length: ' . strlen($base64Data));

                    $altResponse = Http::withHeaders($headers)->asForm()->post($serviceUrl, $altPayload);

                    \Log::info('Base64 BPJS Response Status: ' . $altResponse->status());
                    \Log::info('Base64 BPJS Response Body: ' . $altResponse->body());

                    if ($altResponse->successful()) {
                        return response()->json([
                            'message' => 'Data rekam medis berhasil dikirim ke BPJS (base64 encoded)',
                            'metadata' => [
                                'no_sep' => $payload['noSep'],
                                'jenis_pelayanan' => $payload['jnsPelayanan'],
                                'bulan' => $payload['bulan'],
                                'tahun' => $payload['tahun'],
                                'rumah_sakit' => [
                                    'nama_instansi' => $this->namaInstansi,
                                    'kode_faskes_bpjs' => $this->kodeFaskes,
                                    'kode_kemenkes' => $this->kodeKemenkes
                                ]
                            ],
                            'bpjs_response' => $altResponse->json()
                        ], 200);
                    } else {
                        \Log::error('Base64 encoding also failed: ' . $altResponse->body());

                        // Try with JSON content type
                        \Log::info('Trying with JSON content type...');

                        $jsonPayload = [
                            "noSep" => $payload['noSep'],
                            "jnsPelayanan" => $payload['jnsPelayanan'],
                            "bulan" => $payload['bulan'],
                            "tahun" => $payload['tahun'],
                            "dataMR" => base64_encode($finalData),
                        ];

                        $jsonHeaders = [
                            'X-cons-id'     => $this->consId,
                            'X-timestamp'   => $signatureData['timestamp'],
                            'X-signature'   => $signatureData['signature'],
                            'user_key'      => $this->userKey,
                            'Content-Type'  => 'application/json',
                        ];

                        $jsonResponse = Http::withHeaders($jsonHeaders)->post($serviceUrl, $jsonPayload);

                        \Log::info('JSON BPJS Response Status: ' . $jsonResponse->status());
                        \Log::info('JSON BPJS Response Body: ' . $jsonResponse->body());

                        if ($jsonResponse->successful()) {
                            return response()->json([
                                'message' => 'Data rekam medis berhasil dikirim ke BPJS (JSON format)',
                                'metadata' => [
                                    'no_sep' => $payload['noSep'],
                                    'jenis_pelayanan' => $payload['jnsPelayanan'],
                                    'bulan' => $payload['bulan'],
                                    'tahun' => $payload['tahun'],
                                    'rumah_sakit' => [
                                        'nama_instansi' => $this->namaInstansi,
                                        'kode_faskes_bpjs' => $this->kodeFaskes,
                                        'kode_kemenkes' => $this->kodeKemenkes
                                    ]
                                ],
                                'bpjs_response' => $jsonResponse->json()
                            ], 200);
                        }

                        \Log::error('JSON format also failed: ' . $jsonResponse->body());
                        \Log::error('All attempts failed');
                    }
                }

                return response()->json([
                    'message' => 'Gagal mengirim data ke BPJS',
                    'error' => $response->body(),
                    'status' => $response->status(),
                    'debug_info' => [
                        'service_url' => $serviceUrl,
                        'timestamp' => $signatureData['timestamp'],
                        'response_headers' => $response->headers(),
                        'request_headers' => $headers,
                        'cons_id' => $this->consId,
                        'user_key' => $this->userKey,
                        'timestamp_check' => [
                            'generated' => $signatureData['timestamp'],
                            'current_time' => time(),
                            'diff_seconds' => time() - $signatureData['timestamp']
                        ]
                    ]
                ], $response->status());
            }

        } catch (\Exception $e) {
            \Log::error('BPJS API Error: ' . $e->getMessage());
            \Log::error('BPJS Request Details: ' . json_encode([
                'cons_id' => $this->consId,
                'timestamp' => $signatureData['timestamp'] ?? null,
                'service_url' => $serviceUrl,
                'payload_size' => strlen(json_encode($finalPayload))
            ]));

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengirim data ke BPJS',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'cons_id' => $this->consId,
                    'timestamp' => $signatureData['timestamp'] ?? null,
                    'service_url' => $serviceUrl,
                    'error_type' => class_basename($e)
                ]
            ], 500);
        }
    }

    /**
     * Recursive function to analyze resource structure for JObject vs JArray issues
     */
    private function analyzeResourceStructure($resource, $resourceType, $depth)
    {
        $indent = str_repeat("  ", $depth);
        $depth++;

        // Known fields that should be arrays in FHIR
        $expectedArrayFields = [
            'Patient' => ['identifier', 'name', 'telecom', 'address', 'communication', 'maritalStatus'],
            'Organization' => ['identifier', 'type', 'alias', 'telecom', 'address', 'contact'],
            'Practitioner' => ['identifier', 'name', 'telecom', 'address', 'qualification', 'communication'],
            'Encounter' => ['identifier', 'type', 'participant', 'reason', 'diagnosis', 'serviceProvider'],
            'Condition' => ['identifier', 'category', 'severity', 'bodySite', 'evidence'],
            'MedicationRequest' => ['identifier', 'dosageInstruction', 'dispenseRequest', 'substitution'],
            'Procedure' => ['identifier', 'performer', 'bodySite', 'reasonCode', 'focalDevice'],
            'Composition' => ['attester', 'custodian', 'event', 'section', 'author'],
            'Bundle' => ['identifier', 'entry']
        ];

        foreach ($resource as $key => $value) {
            if (is_null($value) || is_scalar($value)) {
                \Log::info($indent . "$key: " . gettype($value) . " = " . (is_string($value) ? substr($value, 0, 50) . '...' : $value));
                continue;
            }

            $valueType = gettype($value);
            $isArray = is_array($value);

            \Log::info($indent . "$key: $valueType");

            // Check if this field should be an array based on resource type
            if (isset($expectedArrayFields[$resourceType]) && in_array($key, $expectedArrayFields[$resourceType])) {
                if ($isArray) {
                    \Log::info($indent . "  ✅ $resourceType.$key is array with " . count($value) . " items");
                } else {
                    \Log::error($indent . "  ❌ ERROR: $resourceType.$key should be array but is $valueType");
                    \Log::error($indent . "  Value: " . json_encode($value));
                }
            }

            // Special handling for known problem areas
            if ($key === 'section' && $resourceType === 'Composition') {
                if ($isArray) {
                    \Log::info($indent . "  ✅ Composition.section is array");
                } else {
                    \Log::error($indent . "  ❌ Composition.section should be array but is $valueType");
                }
            }

            if ($key === 'diagnosis' && $resourceType === 'Encounter') {
                if ($isArray) {
                    \Log::info($indent . "  ✅ Encounter.diagnosis is array");
                } else {
                    \Log::error($indent . "  ❌ Encounter.diagnosis should be array but is $valueType");
                }
            }

            // Recursively analyze nested objects/arrays
            if ($isArray) {
                \Log::info($indent . "  Analyzing array contents...");
                foreach ($value as $i => $item) {
                    if (is_array($item) || is_object($item)) {
                        \Log::info($indent . "  [$i]:");
                        $this->analyzeResourceStructure($item, $resourceType . '.' . $key . "[$i]", $depth + 1);
                    }
                }
            } elseif (is_object($value) || is_array($value)) {
                \Log::info($indent . "  Analyzing nested object...");
                $this->analyzeResourceStructure($value, $resourceType . '.' . $key, $depth + 1);
            }
        }
    }

    /**
     * Recursively fix nested arrays in FHIR resources to prevent JObject to JArray casting issues
     */
    private function fixNestedArrays(&$resource, $resourceType, $depth = 0)
    {
        if ($depth > 10) return; // Prevent infinite recursion

        // Known FHIR fields that should be arrays (NOT including 'resource' as that should remain an object)
        $arrayFields = [
            'identifier', 'name', 'telecom', 'address', 'communication', 'contact', 'alias', 'type',
            'section', 'entry', 'participant', 'diagnosis', 'reason', 'category', 'performer', 'bodySite',
            'dosageInstruction', 'dispenseRequest', 'substitution', 'qualification', 'maritalStatus',
            'extension', 'modifierExtension', 'contained', 'codings', 'coding', 'target', 'author',
            'attester', 'custodian', 'event', 'serviceProvider', 'account', 'basedOn', 'replaces',
            'partOf', 'item', 'adjudication', 'addItem', 'detail', 'procedure', 'insurance',
            'supportingInfo', 'benefitBalance', 'payment', 'processNote'
        ];

        foreach ($resource as $key => &$value) {
            if (is_array($value)) {
                // Process each item in the array
                foreach ($value as &$item) {
                    if (is_array($item) || is_object($item)) {
                        $this->fixNestedArrays($item, $resourceType . '.' . $key, $depth + 1);
                    }
                }
            } elseif (is_object($value)) {
                // Check if this should be an array based on the field name
                if (in_array($key, $arrayFields)) {
                    \Log::info("Converting $resourceType.$key from object to array");
                    $value = (array)$value;
                    // Re-index to ensure numeric keys
                    $value = array_values($value);
                } else {
                    // Recursively process the object
                    $this->fixNestedArrays($value, $resourceType . '.' . $key, $depth + 1);
                }
            }
        }
    }

    /**
     * Apply aggressive fixes to a specific resource
     */
    private function applyAggressiveFixesToResource(&$resource, $resourceType)
    {
        // Only convert fields that should actually be arrays according to FHIR specifications
        // Core attributes like resourceType, id, active, status, gender, birthDate should remain as single values
        $arrayFields = [
            'identifier', 'name', 'telecom', 'address', 'communication', 'contact',
            'type', 'section', 'entry', 'participant', 'diagnosis', 'reason', 'category',
            'performer', 'bodySite', 'dosageInstruction', 'dispenseRequest', 'substitution',
            'qualification', 'extension', 'modifierExtension', 'contained',
            'coding', 'target', 'author', 'attester', 'custodian', 'event', 'serviceProvider',
            'account', 'basedOn', 'replaces', 'partOf', 'item', 'adjudication', 'addItem',
            'detail', 'insurance', 'supportingInfo', 'benefitBalance', 'payment',
            'processNote', 'alias', 'hospitalization', 'incomingReferral', 'dischargeDisposition'
        ];

        foreach ($arrayFields as $field) {
            if (isset($resource[$field]) && !is_array($resource[$field])) {
                $originalValue = $resource[$field];
                $resource[$field] = (array)$originalValue;

                // Re-index to ensure numeric keys
                if (!empty($resource[$field])) {
                    $resource[$field] = array_values($resource[$field]);
                    \Log::info("Fixed $field from " . gettype($originalValue) . " to array with " . count($resource[$field]) . " items");
                }
            }
        }

        // Additional resource-specific fixes based on the log analysis
        switch ($resourceType) {
            case 'Patient':
                // Ensure Patient.maritalStatus.coding is array
                if (isset($resource['maritalStatus']['coding']) && !is_array($resource['maritalStatus']['coding'])) {
                    $resource['maritalStatus']['coding'] = [(array)$resource['maritalStatus']['coding']];
                    \Log::info("Fixed Patient.maritalStatus.coding structure");
                }
                break;

            case 'Organization':
                // Organization.type is already handled in the main arrayFields loop
                break;

            case 'MedicationRequest':
                // MedicationRequest.identifier is already handled in the main arrayFields loop
                // Remove empty div text
                if (isset($resource['text']['div']) && $resource['text']['div'] === '<div></div>') {
                    $resource['text']['div'] = '';
                    \Log::info("Fixed MedicationRequest empty div");
                }
                break;

            case 'Procedure':
                // Remove empty div text from Procedure
                if (isset($resource['text']['div']) && $resource['text']['div'] === '<div></div>') {
                    $resource['text']['div'] = '';
                    \Log::info("Fixed Procedure empty div");
                }
                break;
        }
    }

    /**
     * Apply aggressive final fixes to ensure no JObject to JArray casting issues remain
     */
    private function applyFinalStructureFixes(&$bundle)
    {
        // Fix Bundle-level identifier first
        if (isset($bundle['identifier']) && !is_array($bundle['identifier'])) {
            $originalIdentifier = $bundle['identifier'];
            $bundle['identifier'] = [$originalIdentifier];
            \Log::info("Bundle-level fix: Converted Bundle.identifier from object to array");
        }

        // Ensure top-level structure is correct
        if (isset($bundle['resourceType']) && $bundle['resourceType'] === 'Bundle' && isset($bundle['entry'])) {
            // Convert Bundle.entry to properly indexed array if needed
            if (!is_array($bundle['entry'])) {
                $bundle['entry'] = (array)$bundle['entry'];
            }

            // Re-index entries to ensure numeric keys
            $bundle['entry'] = array_values($bundle['entry']);

            // Process each entry aggressively
            foreach ($bundle['entry'] as $index => &$entry) {
                if (!isset($entry['resource'])) continue;

                // Handle array-wrapped resources (MedicationRequest and Procedure)
                if (is_array($entry['resource'])) {
                    \Log::info("Entry $index: Found array-wrapped resource with " . count($entry['resource']) . " items");

                    foreach ($entry['resource'] as $resIndex => &$resource) {
                        if (!is_array($resource)) {
                            $resource = (array)$resource;
                        }

                        $resourceType = $resource['resourceType'] ?? 'unknown';

                        // MedicationRequest identifier is handled by applyAggressiveFixesToResource

                        // Special handling for Procedure empty div
                        if ($resourceType === 'Procedure' && isset($resource['text']['div']) && $resource['text']['div'] === '<div></div>') {
                            $resource['text']['div'] = '';
                            \Log::info("Special fix: Removed empty div from Procedure");
                        }

                        // Special handling for Procedure note field - ensure empty string is preserved, not converted to null
                        if ($resourceType === 'Procedure' && isset($resource['note']) && is_array($resource['note'])) {
                            foreach ($resource['note'] as &$noteItem) {
                                if (isset($noteItem['text']) && is_null($noteItem['text'])) {
                                    $noteItem['text'] = '';
                                    \Log::info("Special fix: Converted Procedure note text from null to empty string");
                                }
                            }
                        }

                        // Apply aggressive fixes to nested resource
                        $this->applyAggressiveFixesToResource($resource, $resourceType);
                    }
                } else {
                    // Handle single resource
                    $resource = &$entry['resource'];
                    $resourceType = $resource['resourceType'] ?? 'unknown';

                    // Convert resource to array
                    if (is_object($resource)) {
                        $resource = (array)$resource;
                    }

                    // Special handling for Procedure note field - ensure empty string is preserved, not converted to null
                    if ($resourceType === 'Procedure' && isset($resource['note']) && is_array($resource['note'])) {
                        foreach ($resource['note'] as &$noteItem) {
                            if (isset($noteItem['text']) && is_null($noteItem['text'])) {
                                $noteItem['text'] = '';
                                \Log::info("Special fix: Converted Procedure note text from null to empty string");
                            }
                        }
                    }

                    // Apply aggressive fixes
                    $this->applyAggressiveFixesToResource($resource, $resourceType);
                }
            }
        }
    }

    /**
     * Recursively apply aggressive fixes to all nested structures
     */
    private function applyAggressiveFixesRecursive(&$structure, $depth = 0)
    {
        if ($depth > 15) return; // Prevent infinite recursion

        if (is_array($structure)) {
            foreach ($structure as $key => &$value) {
                if (is_array($value) || is_object($value)) {
                    $this->applyAggressiveFixesRecursive($value, $depth + 1);
                } elseif (is_string($value) && json_decode($value) !== null) {
                    // Check if string contains JSON that might need fixing
                    $jsonArray = json_decode($value, true);
                    if (is_array($jsonArray)) {
                        $structure[$key] = $jsonArray;
                        $this->applyAggressiveFixesRecursive($structure[$key], $depth + 1);
                    }
                }
            }
        } elseif (is_object($structure)) {
            $array = (array)$structure;
            $this->applyAggressiveFixesRecursive($array, $depth + 1);
            $structure = $array;
        }
    }

    /**
     * Apply selective structure fixes - only process single resources, not array-wrapped ones
     */
    private function applySelectiveStructureFixes(&$bundle)
    {
        // Fix Bundle-level identifier if needed
        if (isset($bundle['identifier']) && !is_array($bundle['identifier'])) {
            $originalIdentifier = $bundle['identifier'];
            $bundle['identifier'] = [$originalIdentifier];
            \Log::info("Bundle-level fix: Converted Bundle.identifier from object to array");
        }

        // Process each entry
        if (isset($bundle['entry']) && is_array($bundle['entry'])) {
            foreach ($bundle['entry'] as $index => &$entry) {
                if (!isset($entry['resource'])) continue;

                // Skip array-wrapped resources (like MedicationRequest and Procedure)
                if (is_array($entry['resource'])) {
                    \Log::info("Entry $index: Skipping array-wrapped resource (already correct)");
                    continue;
                }

                // Process single resources (Patient, Organization, Practitioner, etc.)
                $resource = &$entry['resource'];
                if (is_object($resource)) {
                    $resource = (array)$resource;
                }

                $resourceType = $resource['resourceType'] ?? 'unknown';
                \Log::info("Entry $index: Processing single resource type: $resourceType");

                // Apply targeted fixes based on resource type
                switch ($resourceType) {
                    case 'Patient':
                        // Fix Patient fields that should be arrays
                        $this->fixPatientArrays($resource);
                        break;
                    case 'Organization':
                        // Fix Organization fields that should be arrays
                        $this->fixOrganizationArrays($resource);
                        break;
                    case 'Practitioner':
                        // Fix Practitioner fields that should be arrays
                        $this->fixPractitionerArrays($resource);
                        break;
                    case 'Composition':
                        // Fix Composition fields that should be arrays
                        $this->fixCompositionArrays($resource);
                        break;
                    case 'Condition':
                        // Fix Condition fields that should be arrays
                        $this->fixConditionArrays($resource);
                        break;
                    case 'Encounter':
                        // Fix Encounter fields that should be arrays
                        $this->fixEncounterArrays($resource);
                        break;
                }
            }
        }
    }

    /**
     * Fix Patient resource arrays
     */
    private function fixPatientArrays(&$resource)
    {
        $arrayFields = ['identifier', 'name', 'telecom', 'address', 'communication'];
        foreach ($arrayFields as $field) {
            if (isset($resource[$field]) && !is_array($resource[$field])) {
                $resource[$field] = [$resource[$field]];
                \Log::info("Fixed Patient.$field to array");
            }
        }
    }

    /**
     * Fix Organization resource arrays
     */
    private function fixOrganizationArrays(&$resource)
    {
        $arrayFields = ['identifier', 'type', 'alias', 'telecom', 'address', 'contact'];
        foreach ($arrayFields as $field) {
            if (isset($resource[$field]) && !is_array($resource[$field])) {
                $resource[$field] = [$resource[$field]];
                \Log::info("Fixed Organization.$field to array");
            }
        }
    }

    /**
     * Fix Practitioner resource arrays
     */
    private function fixPractitionerArrays(&$resource)
    {
        $arrayFields = ['identifier', 'name', 'telecom', 'address', 'qualification', 'communication'];
        foreach ($arrayFields as $field) {
            if (isset($resource[$field]) && !is_array($resource[$field])) {
                $resource[$field] = [$resource[$field]];
                \Log::info("Fixed Practitioner.$field to array");
            }
        }
    }

    /**
     * Fix Composition resource arrays
     */
    private function fixCompositionArrays(&$resource)
    {
        // Section should remain as object with numeric indices, NOT converted to array
        $arrayFields = ['author', 'attester', 'custodian', 'event'];
        foreach ($arrayFields as $field) {
            if (isset($resource[$field]) && !is_array($resource[$field])) {
                $resource[$field] = [$resource[$field]];
                \Log::info("Fixed Composition.$field to array");
            }
        }

        // Section should be left as-is (object with numeric keys like "0", "1", etc.)
        if (isset($resource['section'])) {
            \Log::info("Leaving Composition.section as object with numeric keys");
        }
    }

    /**
     * Convert sparse composition section keys to consecutive numeric keys for BPJS compatibility
     * Frontend sends sparse keys (10, 20, 30...) to prevent JSON array conversion,
     * we convert them back to consecutive keys (0, 1, 2...) for BPJS API
     */
    private function convertCompositionSectionsToConsecutiveKeys(&$fhirBundle)
    {
        \Log::info('convertCompositionSectionsToConsecutiveKeys: START');

        if (!isset($fhirBundle['entry'])) {
            \Log::info('convertCompositionSectionsToConsecutiveKeys: No entries found');
            return;
        }

        \Log::info('convertCompositionSectionsToConsecutiveKeys: Found ' . count($fhirBundle['entry']) . ' entries');

        foreach ($fhirBundle['entry'] as $entryIndex => &$entry) {
            \Log::info("convertCompositionSectionsToConsecutiveKeys: Processing entry $entryIndex");

            if (!isset($entry['resource'])) {
                \Log::info("convertCompositionSectionsToConsecutiveKeys: Entry $entryIndex has no resource");
                continue;
            }

            $resourceType = $entry['resource']['resourceType'] ?? 'unknown';
            \Log::info("convertCompositionSectionsToConsecutiveKeys: Entry $entryIndex resourceType: $resourceType");

            if ($resourceType === 'Composition') {
                \Log::info("convertCompositionSectionsToConsecutiveKeys: Found Composition in entry $entryIndex");

                if (!isset($entry['resource']['section'])) {
                    \Log::info("convertCompositionSectionsToConsecutiveKeys: Composition in entry $entryIndex has no section");
                    continue;
                }

                $section = $entry['resource']['section'];
                \Log::info("convertCompositionSectionsToConsecutiveKeys: Section type: " . gettype($section));

                // Convert to array regardless of type
                $sectionArray = json_decode(json_encode($section), true);
                \Log::info("convertCompositionSectionsToConsecutiveKeys: Section keys: " . implode(', ', array_keys($sectionArray)));

                // Sort keys to maintain order
                ksort($sectionArray, SORT_NUMERIC);

                // Create new section with consecutive keys as object for BPJS format
                $newSection = new \stdClass();
                $newIndex = 1;

                foreach ($sectionArray as $key => $value) {
                    \Log::info("convertCompositionSectionsToConsecutiveKeys: Converting key '$key' -> '$newIndex'");
                    $newSection->{$newIndex} = $value;
                    $newIndex++;
                }

                $entry['resource']['section'] = $newSection;

                \Log::info("convertCompositionSectionsToConsecutiveKeys: Conversion completed for entry $entryIndex");
                $sectionKeys = array_keys(json_decode(json_encode($newSection), true));
                \Log::info("convertCompositionSectionsToConsecutiveKeys: New section keys: " . implode(', ', $sectionKeys));
                \Log::info("convertCompositionSectionsToConsecutiveKeys: Final section structure: " . json_encode($newSection, JSON_PRETTY_PRINT));
            }
        }

        \Log::info('convertCompositionSectionsToConsecutiveKeys: END');
    }

    /**
     * Fix Condition resource arrays
     */
    private function fixConditionArrays(&$resource)
    {
        $arrayFields = ['category', 'bodySite', 'stage', 'evidence'];
        foreach ($arrayFields as $field) {
            if (isset($resource[$field]) && !is_array($resource[$field])) {
                $resource[$field] = [$resource[$field]];
                \Log::info("Fixed Condition.$field to array");
            }
        }
    }

    /**
     * Fix Encounter resource arrays
     */
    private function fixEncounterArrays(&$resource)
    {
        $arrayFields = ['identifier', 'type', 'participant', 'reason', 'diagnosis', 'serviceProvider', 'incomingReferral', 'hospitalization'];
        foreach ($arrayFields as $field) {
            if (isset($resource[$field]) && !is_array($resource[$field])) {
                $resource[$field] = [$resource[$field]];
                \Log::info("Fixed Encounter.$field to array");
            }
        }
    }

    /**
     * Recursively fix null text values in PHP arrays before JSON encoding
     * This prevents json_encode() from creating "text": null patterns
     */
    private function debugNullTextValues($data, $label, $depth = 0)
    {
        if ($depth > 10) { // Prevent infinite recursion
            return;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($key === 'text' && is_null($value)) {
                    \Log::info("DEBUG $label: Found null text value at depth $depth, key: $key");
                } elseif ($key === 'text') {
                    \Log::info("DEBUG $label: Found text value at depth $depth, key: $key, value: " . json_encode($value));
                } elseif (is_array($value)) {
                    $this->debugNullTextValues($value, $label, $depth + 1);
                } elseif (is_object($value)) {
                    $this->debugNullTextValues((array)$value, $label, $depth + 1);
                }
            }
        } elseif (is_object($data)) {
            $this->debugNullTextValues((array)$data, $label, $depth + 1);
        }
    }

    private function fixNullTextValuesInArray(&$data, $depth = 0)
    {
        if ($depth > 10) { // Prevent infinite recursion
            return;
        }

        if (is_array($data)) {
            foreach ($data as $key => &$value) {
                if ($key === 'text' && is_null($value)) {
                    $value = '';
                    \Log::info("PRE-ENCODING FIX: Converted null text value to empty string at depth $depth");
                } elseif (is_array($value)) {
                    $this->fixNullTextValuesInArray($value, $depth + 1);
                } elseif (is_object($value)) {
                    // Convert object to array for processing
                    $valueArray = (array)$value;
                    $this->fixNullTextValuesInArray($valueArray, $depth + 1);
                    // Convert back to object if needed
                    $value = (object)$valueArray;
                }
            }
        } elseif (is_object($data)) {
            // Convert object to array for processing
            $dataArray = (array)$data;
            $this->fixNullTextValuesInArray($dataArray, $depth + 1);
            // Convert back to object
            $data = (object)$dataArray;
        }
    }

    /**
     * Mendapatkan data ERM yang tersimpan untuk SEP tertentu
     *
     * @param  string $noSep Nomor SEP
     * @return \Illuminate\Http\JsonResponse
     */
    public function getErmData($noSep)
    {
        try {
            // Validate input parameter
            if (empty($noSep)) {
                return response()->json([
                    'message' => 'Parameter SEP tidak boleh kosong',
                    'data' => null
                ], 400);
            }

            \Log::info("Getting ERM data for SEP: " . $noSep);

            // Use try-catch specifically for database query
            try {
                $ermData = RsiaErmBpjs::where('nosep', $noSep)->first();
            } catch (\Illuminate\Database\QueryException $qe) {
                \Log::error('Database query error for ERM data: ' . $qe->getMessage());
                return response()->json([
                    'message' => 'Database error saat mengambil data ERM',
                    'error' => 'Query execution failed',
                    'data' => null
                ], 500);
            }

            if (!$ermData) {
                \Log::info("No ERM data found for SEP: " . $noSep);
                return response()->json([
                    'message' => 'Data ERM tidak ditemukan untuk SEP: ' . $noSep,
                    'data' => null
                ], 404);
            }

            \Log::info("Found ERM data for SEP: " . $noSep);

            // Safely prepare response data
            $response = [
                'data' => [
                    'nosep' => $ermData->nosep,
                    'created_at' => $ermData->created_at ? $ermData->created_at->toISOString() : null,
                    'updated_at' => $ermData->updated_at ? $ermData->updated_at->toISOString() : null,
                ]
            ];

            // Add erm_request if it exists and is valid JSON
            try {
                $response['data']['erm_request'] = $ermData->erm_request;
            } catch (\Exception $e) {
                \Log::warning('Error processing erm_request for SEP ' . $noSep . ': ' . $e->getMessage());
                $response['data']['erm_request'] = null;
            }

            // Add erm_response if it exists and is valid JSON
            try {
                $response['data']['erm_response'] = $ermData->erm_response;
            } catch (\Exception $e) {
                \Log::warning('Error processing erm_response for SEP ' . $noSep . ': ' . $e->getMessage());
                $response['data']['erm_response'] = null;
            }

            \Log::info("Successfully prepared ERM response for SEP: " . $noSep);

            return response()->json([
                'message' => 'Data ERM ditemukan',
                'data' => $response['data']
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Unexpected error retrieving ERM data for SEP ' . $noSep . ': ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil data ERM',
                'error' => 'Internal server error',
                'data' => null
            ], 500);
        }
    }

    /**
     * Mendapatkan hanya bundle ERM asli (sebelum enkripsi) untuk debugging
     *
     * @param  string $noSep Nomor SEP
     * @return \Illuminate\Http\JsonResponse
     */
    public function getErmBundle($noSep)
    {
        try {
            $ermData = RsiaErmBpjs::where('nosep', $noSep)->first();

            if (!$ermData || !$ermData->erm_request) {
                return response()->json([
                    'message' => 'Bundle ERM tidak ditemukan untuk SEP: ' . $noSep,
                    'bundle' => null
                ], 404);
            }

            $requestData = $ermData->erm_request;
            $bundle = $requestData['bundle'] ?? null;

            return response()->json([
                'message' => 'Bundle ERM ditemukan',
                'nosep' => $noSep,
                'metadata' => $requestData['metadata'] ?? [],
                'timestamp' => $requestData['timestamp'] ?? null,
                'bundle' => $bundle
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error retrieving ERM bundle: ' . $e->getMessage());

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil bundle ERM',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan hanya response BPJS untuk debugging
     *
     * @param  string $noSep Nomor SEP
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBpjsResponse($noSep)
    {
        try {
            $ermData = RsiaErmBpjs::where('nosep', $noSep)->first();

            if (!$ermData || !$ermData->erm_response) {
                return response()->json([
                    'message' => 'Response BPJS tidak ditemukan untuk SEP: ' . $noSep,
                    'response' => null
                ], 404);
            }

            $responseData = $ermData->erm_response;
            $response = $responseData['response'] ?? null;

            return response()->json([
                'message' => 'Response BPJS ditemukan',
                'nosep' => $noSep,
                'metadata' => $responseData['metadata'] ?? [],
                'timestamp' => $responseData['timestamp'] ?? null,
                'response' => $response
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error retrieving BPJS response: ' . $e->getMessage());

            return response()->json([
                'message' => 'Terjadi kesalahan saat mengambil response BPJS',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}