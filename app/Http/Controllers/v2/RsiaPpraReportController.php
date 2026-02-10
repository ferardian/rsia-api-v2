<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;
use Carbon\Carbon;

class RsiaPpraReportController extends Controller
{
    public function laporan(Request $request)
    {
        $start = $request->query('tgl_awal', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $end = $request->query('tgl_akhir', Carbon::now()->format('Y-m-d'));
        $kd_sps = $request->query('kd_sps');
        $kd_dokter = $request->query('kd_dokter');
        $search = $request->query('search');

        // Query resep yang obatnya terdaftar di ppra_mapping
        $query = DB::table('resep_obat')
            ->join('reg_periksa', 'resep_obat.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
            ->join('detail_pemberian_obat', function($join) {
                $join->on('resep_obat.no_rawat', '=', 'detail_pemberian_obat.no_rawat')
                    ->on('resep_obat.tgl_perawatan', '=', 'detail_pemberian_obat.tgl_perawatan')
                    ->on('resep_obat.jam', '=', 'detail_pemberian_obat.jam');
            })
            ->join('databarang', 'detail_pemberian_obat.kode_brng', '=', 'databarang.kode_brng')
            ->join('rsia_ppra_mapping_obat', 'databarang.kode_brng', '=', 'rsia_ppra_mapping_obat.kode_brng')
            ->leftJoin('resep_dokter', function($join) {
                $join->on('resep_obat.no_resep', '=', 'resep_dokter.no_resep')
                    ->on('detail_pemberian_obat.kode_brng', '=', 'resep_dokter.kode_brng');
            })
            ->leftJoin('rsia_ppra_resep_verifikasi', function($join) {
                $join->on('resep_obat.no_resep', '=', 'rsia_ppra_resep_verifikasi.no_resep')
                    ->on('detail_pemberian_obat.kode_brng', '=', 'rsia_ppra_resep_verifikasi.kode_brng');
            })
            ->whereBetween('resep_obat.tgl_perawatan', [$start, $end])
            ->where('resep_obat.status', 'like', 'ranap%');

        if ($kd_sps) {
            $query->where('dokter.kd_sps', $kd_sps);
        }

        if ($kd_dokter) {
            $query->where('reg_periksa.kd_dokter', $kd_dokter);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pasien.nm_pasien', 'like', "%{$search}%")
                    ->orWhere('pasien.no_rkm_medis', 'like', "%{$search}%")
                    ->orWhere('reg_periksa.no_rawat', 'like', "%{$search}%");
            });
        }

        $query->select([
            'resep_obat.no_resep',
            'resep_obat.no_rawat',
            'pasien.nm_pasien',
            'pasien.no_rkm_medis',
            'dokter.nm_dokter',
            'pasien.tgl_lahir',
            'databarang.kode_brng',
            'databarang.nama_brng',
            'rsia_ppra_mapping_obat.rute_pemberian',
            'rsia_ppra_mapping_obat.nilai_ddd_who',
            'resep_dokter.aturan_pakai as aturan_pakai_dokter',
            'detail_pemberian_obat.jml',
            'rsia_ppra_resep_verifikasi.aturan_pakai as aturan_pakai_verif',
            'rsia_ppra_resep_verifikasi.status_telaah',
            'rsia_ppra_resep_verifikasi.status_persetujuan',
            'rsia_ppra_resep_verifikasi.catatan_telaah',
            'rsia_ppra_resep_verifikasi.catatan_persetujuan',
            'resep_obat.tgl_perawatan',
            'resep_obat.jam',
            'databarang.kode_sat',
            'databarang.isi'
        ]);

        $results = $query->get();

        if ($results->isEmpty()) {
            return ApiResponse::successWithData([], 'Laporan PPRA berhasil diambil');
        }

        // 1. Collect all unique no_rawat
        $noRawatList = $results->pluck('no_rawat')->unique()->toArray();

        // 2. Fetch all diagnoses in one query
        $diagnosaResults = DB::table('diagnosa_pasien')
            ->join('penyakit', 'diagnosa_pasien.kd_penyakit', '=', 'penyakit.kd_penyakit')
            ->whereIn('no_rawat', $noRawatList)
            ->orderBy('prioritas', 'asc')
            ->select('no_rawat', 'nm_penyakit')
            ->get()
            ->groupBy('no_rawat');

        // 3. Fetch all room stays in one query for LOS calculation
        $ranapResults = DB::table('kamar_inap')
            ->whereIn('no_rawat', $noRawatList)
            ->select('no_rawat', 'tgl_masuk', 'jam_masuk', 'tgl_keluar', 'jam_keluar')
            ->orderBy('tgl_masuk', 'asc')
            ->get()
            ->groupBy('no_rawat');

        // Process data for grouping and calculations
        $data = [];
        foreach ($results as $item) {
            $no_rawat = $item->no_rawat;
            
            if (!isset($data[$no_rawat])) {
                // Lookup Diagnosa from bulk results
                $diagnosa = $diagnosaResults->get($no_rawat, collect())->pluck('nm_penyakit')->toArray();

                // Lookup LOS from bulk results
                $ranap = $ranapResults->get($no_rawat, collect());
                
                $los = 0;
                $tgl_masuk = '-';
                if ($ranap->isNotEmpty()) {
                    $first = $ranap->first();
                    $last = $ranap->last();
                    
                    if ($first->tgl_masuk && $first->tgl_masuk != '0000-00-00') {
                        $tgl_masuk = $first->tgl_masuk;
                        $start_date = Carbon::parse($first->tgl_masuk . ' ' . $first->jam_masuk);
                        
                        if ($last->tgl_keluar && $last->tgl_keluar != '0000-00-00') {
                            $end_date = Carbon::parse($last->tgl_keluar . ' ' . $last->jam_keluar);
                        } else {
                            $end_date = Carbon::now();
                        }
                        
                        $los = (int) $start_date->diffInDays($end_date);
                        if ($los <= 0) $los = 1; 
                    }
                }

                $age = Carbon::parse($item->tgl_lahir)->age;

                $data[$no_rawat] = [
                    'no_rawat' => $no_rawat,
                    'nm_pasien' => $item->nm_pasien . ' (' . $age . ' th)',
                    'no_rkm_medis' => $item->no_rkm_medis,
                    'nm_dokter' => $item->nm_dokter,
                    'tgl_masuk' => $tgl_masuk,
                    'diagnosa' => implode(', ', $diagnosa),
                    'los' => $los,
                    'antibiotik' => []
                ];
            }

            // Antibiotic grouping inside patient
            $ab_key = $item->nama_brng . '_' . $item->rute_pemberian . '_' . $item->tgl_perawatan;
            
            // Logic: Utama Resep Dokter, Fallback Verifikasi
            $original_aturan = $item->aturan_pakai_dokter;
            if (empty($original_aturan)) {
                $original_aturan = $item->aturan_pakai_verif;
            }

            // Parse dosage using regex
            $parsed = $this->parseAturanPakai($original_aturan);
            $aturan_pakai = $parsed['display'];
            $magnitude = $parsed['magnitude'];

            if (!isset($data[$no_rawat]['antibiotik'][$ab_key])) {
                $data[$no_rawat]['antibiotik'][$ab_key] = [
                    'no_resep' => $item->no_resep,
                    'kode_brng' => $item->kode_brng,
                    'nama_brng' => $item->nama_brng,
                    'tgl_pemberian' => $item->tgl_perawatan,
                    'rute' => $item->rute_pemberian,
                    'aturan_pakai' => $aturan_pakai,
                    'status_telaah' => $item->status_telaah ?? 'BELUM',
                    'status_persetujuan' => $item->status_persetujuan ?? 'PENDING',
                    'catatan_telaah' => $item->catatan_telaah,
                    'catatan_persetujuan' => $item->catatan_persetujuan,
                    'jml_total' => 0,
                    'ddd_factor' => (float) $item->nilai_ddd_who,
                    'satuan' => $item->kode_sat,
                    'isi' => $item->isi,
                    'magnitude' => $magnitude
                ];
            }
            $data[$no_rawat]['antibiotik'][$ab_key]['jml_total'] += $item->jml;
        }

        // Final formatting for Frontend Table
        $finalData = [];
        foreach ($data as $rn) {
            $isFirstInRawat = true;
            foreach ($rn['antibiotik'] as $ab) {
                // Calculate DDD
                // Logic: prefer parsed magnitude (from Aturan Pakai) if available and > 0
                // This ensures calculations follow verified dosage rules rather than just dispensed quantity.
                $use_magnitude = $ab['magnitude'] > 0;
                $total_converted = $use_magnitude ? $ab['magnitude'] : ($ab['jml_total'] * $ab['isi']);
                
                // Check if aturan_pakai is empty or just '-'
                $hasValidDosage = !empty($ab['aturan_pakai']) && $ab['aturan_pakai'] !== '-';
                
                $ddd = 0;
                if ($hasValidDosage && $ab['ddd_factor'] > 0) {
                    $ddd = $total_converted / $ab['ddd_factor'];
                }

                $row = [
                    'no_rawat' => $rn['no_rawat'],
                    'no_resep' => $ab['no_resep'],
                    'kode_brng' => $ab['kode_brng'],
                    'nm_pasien' => $rn['nm_pasien'],
                    'no_rkm_medis' => $rn['no_rkm_medis'],
                    'nm_dokter' => $rn['nm_dokter'],
                    'tgl_masuk' => $rn['tgl_masuk'],
                    'diagnosa' => $rn['diagnosa'],
                    'los' => $rn['los'] . ' hari',
                    'jenis_ab' => $ab['nama_brng'],
                    'tgl_pemberian' => $ab['tgl_pemberian'],
                    'rute' => $ab['rute'],
                    'penggunaan_harian' => $ab['aturan_pakai'],
                    'status_telaah' => $ab['status_telaah'],
                    'status_persetujuan' => $ab['status_persetujuan'],
                    'catatan_telaah' => $ab['catatan_telaah'],
                    'catatan_persetujuan' => $ab['catatan_persetujuan'],
                    'total_pakai' => $ab['jml_total'] . ' ' . $ab['satuan'],
                    'jml_total' => (float) $ab['jml_total'],
                    'satuan' => $ab['satuan'],
                    'ddd_factor' => (float) $ab['ddd_factor'],
                    'total_ddd' => $hasValidDosage ? round($ddd, 2) : null,
                    'is_new_patient' => $isFirstInRawat
                ];

                $finalData[] = $row;
                $isFirstInRawat = false;
            }
        }

        // Calculate status metrics for dashboard
        $totalResep = count($finalData);
        $verifiedCount = 0;
        $pendingVerifCount = 0;
        $approvedCount = 0;
        $pendingApprovalCount = 0;

        foreach ($finalData as $item) {
            // Verification status (Telaah)
            if ($item['status_telaah'] === 'SESUAI' || $item['status_telaah'] === 'Sudah Ditelaah') {
                $verifiedCount++;
            } else {
                $pendingVerifCount++;
            }

            // Approval status (Persetujuan)
            if ($item['status_persetujuan'] === 'ACC' || $item['status_persetujuan'] === 'Disetujui') {
                $approvedCount++;
            } else {
                $pendingApprovalCount++;
            }
        }

        $metrics = [
            'total_resep' => $totalResep,
            'verification' => [
                'verified' => $verifiedCount,
                'pending' => $pendingVerifCount
            ],
            'approval' => [
                'approved' => $approvedCount,
                'pending' => $pendingApprovalCount
            ]
        ];

        return ApiResponse::successWithData([
            'data' => $finalData,
            'metrics' => $metrics
        ], 'Laporan PPRA berhasil diambil');
    }

    public function getSoapSuggestions(Request $request)
    {
        $no_rawat = $request->query('no_rawat');
        $kode_brng = $request->query('kode_brng');

        if (!$no_rawat || !$kode_brng) {
            return ApiResponse::error('Parameter no_rawat dan kode_brng wajib diisi', 422);
        }

        // Get medicine name
        $obat = DB::table('databarang')
            ->where('kode_brng', $kode_brng)
            ->select('nama_brng')
            ->first();

        if (!$obat) {
            return ApiResponse::error('Obat tidak ditemukan', 404);
        }

        // Use lenient matching for the drug name
        $fullName = strtolower($obat->nama_brng);
        $firstWord = explode(' ', $fullName)[0];
        
        // Define match criteria: full first word OR first 4 chars if first word is long
        $matchCriteria = [$firstWord];
        if (strlen($firstWord) >= 5) {
            $matchCriteria[] = substr($firstWord, 0, 4);
        }
        
        // Fetch RTL from SOAP Ranap
        $soapEntries = DB::table('pemeriksaan_ranap')
            ->where('no_rawat', $no_rawat)
            ->orderBy('tgl_perawatan', 'desc')
            ->orderBy('jam_rawat', 'desc')
            ->select('tgl_perawatan', 'jam_rawat', 'rtl')
            ->get();

        $suggestions = [];
        
        // Regex to find dosage patterns:
        // 1. Frequency format: 2x500mg, 3x1 tab, 1x0.5 ml
        // 2. Interval format: 700mg/12jam, 2.5mg/8jam
        // 3. One-time (stat) format: 1gr ekstra, 500mg extra
        $dosageRegex = '/(\d+(?:[\.,]\d+)?\s*x\s*\d+(?:[\.,]\d+)?\s*[a-z]+|\d+(?:[\.,]\d+)?\s*[a-z]+\s*\/\s*\d+\s*jam|\d+(?:[\.,]\d+)?\s*[a-z]+\s*(?:ekstra|extra))/i';

        foreach ($soapEntries as $soap) {
            if (empty($soap->rtl)) continue;

            $lines = explode("\n", $soap->rtl);
            foreach ($lines as $line) {
                $lineLower = strtolower($line);
                // Check if line contains any of the match criteria (lenient match)
                $isMatch = false;
                foreach ($matchCriteria as $criterion) {
                    if (strpos($lineLower, $criterion) !== false) {
                        $isMatch = true;
                        break;
                    }
                }

                if ($isMatch) {
                    if (preg_match($dosageRegex, $line, $matches)) {
                        $suggestions[] = [
                            'tgl' => $soap->tgl_perawatan,
                            'jam' => $soap->jam_rawat,
                            'raw_text' => trim($line),
                            'suggestion' => $matches[1]
                        ];
                    }
                }
            }
        }

        return ApiResponse::successWithData(array_values($suggestions), 'Data saran SOAP berhasil diambil');
    }

    public function rekapBulanan(Request $request)
    {
        $tahun = $request->query('tahun', date('Y'));
        
        $results = DB::table('detail_pemberian_obat')
            ->join('resep_obat', function($join) {
                $join->on('resep_obat.no_rawat', '=', 'detail_pemberian_obat.no_rawat')
                    ->on('resep_obat.tgl_perawatan', '=', 'detail_pemberian_obat.tgl_perawatan')
                    ->on('resep_obat.jam', '=', 'detail_pemberian_obat.jam');
            })
            ->join('databarang', 'detail_pemberian_obat.kode_brng', '=', 'databarang.kode_brng')
            ->join('rsia_ppra_mapping_obat', 'databarang.kode_brng', '=', 'rsia_ppra_mapping_obat.kode_brng')
            ->leftJoin('resep_dokter', function($join) {
                $join->on('resep_obat.no_resep', '=', 'resep_dokter.no_resep')
                    ->on('detail_pemberian_obat.kode_brng', '=', 'resep_dokter.kode_brng');
            })
            ->leftJoin('rsia_ppra_resep_verifikasi', function($join) {
                $join->on('resep_obat.no_resep', '=', 'rsia_ppra_resep_verifikasi.no_resep')
                    ->on('detail_pemberian_obat.kode_brng', '=', 'rsia_ppra_resep_verifikasi.kode_brng');
            })
            ->whereYear('resep_obat.tgl_perawatan', $tahun)
            ->where('resep_obat.status', 'like', 'ranap%')
            ->select([
                'databarang.nama_brng',
                'databarang.kode_brng',
                'databarang.isi',
                'rsia_ppra_mapping_obat.nilai_ddd_who',
                'resep_dokter.aturan_pakai as aturan_pakai_dokter',
                'rsia_ppra_resep_verifikasi.aturan_pakai as aturan_pakai_verif',
                'detail_pemberian_obat.jml',
                'resep_obat.tgl_perawatan',
                'resep_obat.no_rawat'
            ])
            ->get();
        
        $data = [];
        $daily_logs = []; // key: [kode_brng]_[no_rawat]_[tgl_perawatan]

        foreach ($results as $item) {
            $key = "{$item->kode_brng}_{$item->no_rawat}_{$item->tgl_perawatan}";
            if (!isset($daily_logs[$key])) {
                $original_aturan = $item->aturan_pakai_dokter ?: $item->aturan_pakai_verif;
                $parsed = $this->parseAturanPakai($original_aturan);
                
                $daily_logs[$key] = [
                    'kode_brng' => $item->kode_brng,
                    'nama_brng' => $item->nama_brng,
                    'bulan' => (int) date('n', strtotime($item->tgl_perawatan)),
                    'aturan_pakai' => $original_aturan,
                    'magnitude' => $parsed['magnitude'],
                    'isi' => $item->isi,
                    'ddd_factor' => (float) $item->nilai_ddd_who,
                    'jml_total' => 0
                ];
            }
            $daily_logs[$key]['jml_total'] += $item->jml;
        }

        foreach ($daily_logs as $log) {
            if (!isset($data[$log['kode_brng']])) {
                $data[$log['kode_brng']] = [
                    'nama_brng' => $log['nama_brng'],
                    'kode_brng' => $log['kode_brng'],
                    'months' => array_combine(range(1, 12), array_fill(0, 12, 0))
                ];
            }

            // Skip DDD calculation if no valid dosage rules
            $hasValidDosage = isset($log['aturan_pakai']) && !empty($log['aturan_pakai']) && $log['aturan_pakai'] !== '-';
            if (!$hasValidDosage) {
                continue; // Skip this entry
            }

            $use_magnitude = $log['magnitude'] > 0;
            $total_converted = $use_magnitude ? $log['magnitude'] : ($log['jml_total'] * $log['isi']);
            
            $ddd = $log['ddd_factor'] > 0 ? ($total_converted / $log['ddd_factor']) : 0;
            $data[$log['kode_brng']]['months'][$log['bulan']] += $ddd;
        }

        // Final rounding
        $finalData = array_values($data);
        foreach ($finalData as &$medicine) {
            foreach ($medicine['months'] as $m => $val) {
                $medicine['months'][$m] = round($val, 2);
            }
        }

        return ApiResponse::successWithData($finalData, 'Rekap bulanan PPRA berhasil diambil');
    }

    private function parseAturanPakai($string)
    {
        if (empty($string)) return ['display' => '-', 'magnitude' => 0];

        // Normalize string for consistent parsing
        $cleanStr = trim($string);
        
        // Pattern 1: Frequency x Dosage (e.g., 3x500mg, 1 x 500 mg)
        $p1 = '/(\d+(?:[\.,]\d+)?)\s*x\s*(\d+(?:[\.,]\d+)?)\s*([a-z]+)/i';
        
        // Pattern 2: Dosage / Interval (e.g., 330mg/8jam, 1gr / 12 jam)
        $p2 = '/(\d+(?:[\.,]\d+)?)\s*([a-z]+)\s*\/\s*(\d+)\s*jam/i';
        
        // Pattern 3: Dosage ekstra/extra (e.g., 1gr ekstra, 500mg extra)
        $p3 = '/(\d+(?:[\.,]\d+)?)\s*([a-z]+)\s*(?:ekstra|extra)/i';

        if (preg_match($p1, $cleanStr, $matches)) {
            $freq = (float) str_replace(',', '.', $matches[1]);
            $dose = (float) str_replace(',', '.', $matches[2]);
            $unit = $matches[3];
            return $this->formatTotalDose($freq, $dose, $unit);
        } elseif (preg_match($p2, $cleanStr, $matches)) {
            $dose = (float) str_replace(',', '.', $matches[1]);
            $unit = $matches[2];
            $interval = (int) $matches[3];
            
            if ($interval > 0) {
                $freq = 24 / $interval;
                return $this->formatTotalDose($freq, $dose, $unit);
            }
        } elseif (preg_match($p3, $cleanStr, $matches)) {
            $dose = (float) str_replace(',', '.', $matches[1]);
            $unit = $matches[2];
            $freq = 1; // Ekstra means one time
            return $this->formatTotalDose($freq, $dose, $unit);
        }

        return ['display' => $string, 'magnitude' => 0];
    }

    private function formatTotalDose($freq, $dose, $unit)
    {
        $total = $freq * $dose;
        $totalStr = $total . ' ' . $unit;
        
        $magnitude = 0;
        $u = strtolower($unit);
        if ($u == 'mg') {
            $magnitude = $total / 1000; // Convert to grams
        } elseif ($u == 'gr' || $u == 'gram') {
            $magnitude = $total;
        }

        if (strtolower($unit) == 'mg' && $total >= 1000) {
            $totalStr = ($total / 1000) . ' gr';
        } elseif (strtolower($unit) == 'gr' && $total < 1) {
            $totalStr = ($total * 1000) . ' mg';
        }

        return [
            'display' => "{$freq} x\n{$dose} {$unit} =\n{$totalStr}",
            'magnitude' => (float) $magnitude
        ];
    }
}
