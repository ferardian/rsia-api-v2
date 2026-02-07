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
        $start = $request->query('tgl_start', date('Y-m-01'));
        $end = $request->query('tgl_end', date('Y-m-d'));

        // Query resep yang obatnya terdaftar di ppra_mapping
        $query = DB::table('resep_obat')
            ->join('reg_periksa', 'resep_obat.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->join('resep_dokter', 'resep_obat.no_resep', '=', 'resep_dokter.no_resep')
            ->join('rsia_ppra_mapping_obat', 'resep_dokter.kode_brng', '=', 'rsia_ppra_mapping_obat.kode_brng')
            ->join('databarang', 'resep_dokter.kode_brng', '=', 'databarang.kode_brng')
            ->leftJoin('rsia_ppra_resep_verifikasi', function($join) {
                $join->on('resep_obat.no_resep', '=', 'rsia_ppra_resep_verifikasi.no_resep')
                    ->on('resep_dokter.kode_brng', '=', 'rsia_ppra_resep_verifikasi.kode_brng');
            })
            ->whereBetween('resep_obat.tgl_perawatan', [$start, $end])
            ->where('reg_periksa.status_lanjut', 'Ranap')
            ->select([
                'resep_obat.no_resep',
                'resep_obat.no_rawat',
                'pasien.nm_pasien',
                'pasien.no_rkm_medis',
                'pasien.tgl_lahir',
                'databarang.kode_brng',
                'databarang.nama_brng',
                'rsia_ppra_mapping_obat.rute_pemberian',
                'rsia_ppra_mapping_obat.nilai_ddd_who',
                'resep_dokter.aturan_pakai as aturan_pakai_dokter',
                'resep_dokter.jml',
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

        // Process data for grouping and calculations
        $data = [];
        foreach ($results as $item) {
            $no_rawat = $item->no_rawat;
            
            if (!isset($data[$no_rawat])) {
                // Get Diagnosa
                $diagnosa = DB::table('diagnosa_pasien')
                    ->join('penyakit', 'diagnosa_pasien.kd_penyakit', '=', 'penyakit.kd_penyakit')
                    ->where('no_rawat', $no_rawat)
                    ->orderBy('prioritas', 'asc')
                    ->pluck('nm_penyakit')
                    ->toArray();

                // Get LOS
                $ranap = DB::table('kamar_inap')
                    ->where('no_rawat', $no_rawat)
                    ->select('tgl_masuk', 'jam_masuk', 'tgl_keluar', 'jam_keluar')
                    ->orderBy('tgl_masuk', 'asc')
                    ->get();
                
                $los = 0;
                if ($ranap->isNotEmpty()) {
                    $first = $ranap->first();
                    $last = $ranap->last();
                    
                    // Validate tgl_masuk
                    if ($first->tgl_masuk && $first->tgl_masuk != '0000-00-00') {
                        $start_date = Carbon::parse($first->tgl_masuk . ' ' . $first->jam_masuk);
                        
                        // Use tgl_keluar if available and valid, otherwise use now
                        if ($last->tgl_keluar && $last->tgl_keluar != '0000-00-00') {
                            $end_date = Carbon::parse($last->tgl_keluar . ' ' . $last->jam_keluar);
                        } else {
                            $end_date = Carbon::now();
                        }
                        
                        $los = (int) $start_date->diffInDays($end_date);
                        if ($los <= 0) $los = 1; // Minimal 1 day
                    } else {
                        $los = '-';
                    }
                }

                $age = Carbon::parse($item->tgl_lahir)->age;

                $data[$no_rawat] = [
                    'no_rawat' => $no_rawat,
                    'nm_pasien' => $item->nm_pasien . ' (' . $age . ' th)',
                    'no_rkm_medis' => $item->no_rkm_medis,
                    'diagnosa' => implode(', ', $diagnosa),
                    'los' => $los,
                    'antibiotik' => []
                ];
            }

            // Antibiotic grouping inside patient
            $ab_key = $item->nama_brng . '_' . $item->rute_pemberian;
            
            // Logic: Utama Resep Dokter, Fallback Verifikasi
            $original_aturan = $item->aturan_pakai_dokter;
            if (empty($original_aturan)) {
                $original_aturan = $item->aturan_pakai_verif;
            }

            // Parse dosage using regex
            $aturan_pakai = $this->parseAturanPakai($original_aturan);

            if (!isset($data[$no_rawat]['antibiotik'][$ab_key])) {
                $data[$no_rawat]['antibiotik'][$ab_key] = [
                    'no_resep' => $item->no_resep,
                    'kode_brng' => $item->kode_brng,
                    'nama_brng' => $item->nama_brng,
                    'rute' => $item->rute_pemberian,
                    'aturan_pakai' => $aturan_pakai,
                    'status_telaah' => $item->status_telaah ?? 'BELUM',
                    'status_persetujuan' => $item->status_persetujuan ?? 'PENDING',
                    'catatan_telaah' => $item->catatan_telaah,
                    'catatan_persetujuan' => $item->catatan_persetujuan,
                    'jml_total' => 0,
                    'ddd_factor' => (float) $item->nilai_ddd_who,
                    'satuan' => $item->kode_sat,
                    'isi' => $item->isi
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
                $total_converted = $ab['jml_total']; 
                
                $ddd = 0;
                if ($ab['ddd_factor'] > 0) {
                    $ddd = ($total_converted * $ab['isi']) / $ab['ddd_factor'];
                }

                $row = [
                    'no_rawat' => $rn['no_rawat'],
                    'no_resep' => $ab['no_resep'],
                    'kode_brng' => $ab['kode_brng'],
                    'nm_pasien' => $isFirstInRawat ? $rn['nm_pasien'] : '',
                    'no_rkm_medis' => $isFirstInRawat ? $rn['no_rkm_medis'] : '',
                    'diagnosa' => $isFirstInRawat ? $rn['diagnosa'] : '',
                    'los' => $isFirstInRawat ? $rn['los'] . ' hari' : '',
                    'jenis_ab' => $ab['nama_brng'],
                    'rute' => $ab['rute'],
                    'penggunaan_harian' => $ab['aturan_pakai'],
                    'status_telaah' => $ab['status_telaah'],
                    'status_persetujuan' => $ab['status_persetujuan'],
                    'catatan_telaah' => $ab['catatan_telaah'],
                    'catatan_persetujuan' => $ab['catatan_persetujuan'],
                    'total_pakai' => $ab['jml_total'] . ' ' . $ab['satuan'],
                    'total_ddd' => round($ddd, 2),
                    'is_new_patient' => $isFirstInRawat
                ];

                $finalData[] = $row;
                $isFirstInRawat = false;
            }
        }

        return ApiResponse::successWithData($finalData, 'Laporan PPRA berhasil diambil');
    }

    private function parseAturanPakai($string)
    {
        if (empty($string)) return '-';

        // Normalize string for consistent parsing
        $cleanStr = trim($string);
        
        // Regex to capture Frequency (f), Dosage (d), and Unit (u)
        // Expected patterns: 3x500mg, 2 x 1 gr, 1x0,5 tab, etc.
        // Captures: [1] => Frequency, [2] => Dosage, [3] => Unit
        $pattern = '/(\d+(?:[\.,]\d+)?)\s*x\s*(\d+(?:[\.,]\d+)?)\s*([a-z]+)/i';

        if (preg_match($pattern, $cleanStr, $matches)) {
            $freq = str_replace(',', '.', $matches[1]);
            $dose = str_replace(',', '.', $matches[2]);
            $unit = $matches[3];

            $freq = (float) $freq;
            $dose = (float) $dose;

            $total = $freq * $dose;
            
            // Format Total based on unit
            $totalStr = $total . ' ' . $unit;
            if (strtolower($unit) == 'mg' && $total >= 1000) {
                $totalStr = ($total / 1000) . ' gr';
            } elseif (strtolower($unit) == 'gr' && $total < 1) {
                $totalStr = ($total * 1000) . ' mg';
            }

            // Return multi-line string for white-space: pre-line
            return "{$freq} x\n{$dose} {$unit} =\n{$totalStr}";
        }

        return $string; // Fallback to original string if not parsable
    }
}
