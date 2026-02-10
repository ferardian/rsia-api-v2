<?php

namespace App\Http\Controllers\v2\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\ApiResponse;

class RsiaMorbiditasRalanController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month', date('m'));
        $year = $request->query('year', date('Y'));

        // Define age groups mapping for Ralan
        // Note: Using sttsumur (Th, Bl, Hr) and umurdaftar
        $ageGroups = [
            'age_lt_1h'    => ['label' => '< 1 Jam',            'condition' => 'FALSE'], // Not tracked in Ralan
            'age_1_23h'   => ['label' => '1 - 23 Jam',         'condition' => 'FALSE'], // Not tracked in Ralan
            'age_1_7d'    => ['label' => '1 - 7 Hari',         'condition' => "base.sttsumur = 'Hr' AND base.umurdaftar <= 7"],
            'age_8_28d'   => ['label' => '8 - 28 Hari',        'condition' => "base.sttsumur = 'Hr' AND base.umurdaftar BETWEEN 8 AND 28"],
            'age_29d_3m'  => ['label' => '29 Hari - <3 Bulan', 'condition' => "(base.sttsumur = 'Hr' AND base.umurdaftar > 28) OR (base.sttsumur = 'Bl' AND base.umurdaftar < 3)"],
            'age_3_6m'    => ['label' => '3 - <6 Bulan',       'condition' => "base.sttsumur = 'Bl' AND base.umurdaftar BETWEEN 3 AND 5"],
            'age_6_11m'   => ['label' => '6 - 11 Bulan',       'condition' => "base.sttsumur = 'Bl' AND base.umurdaftar BETWEEN 6 AND 11"],
            'age_1_4y'    => ['label' => '1 - 4 Tahun',        'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 1 AND 4"],
            'age_5_9y'    => ['label' => '5 - 9 Tahun',        'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 5 AND 9"],
            'age_10_14y'  => ['label' => '10 - 14 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 10 AND 14"],
            'age_15_19y'  => ['label' => '15 - 19 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 15 AND 19"],
            'age_20_24y'  => ['label' => '20 - 24 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 20 AND 24"],
            'age_25_29y'  => ['label' => '25 - 29 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 25 AND 29"],
            'age_30_34y'  => ['label' => '30 - 34 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 30 AND 34"],
            'age_35_39y'  => ['label' => '35 - 39 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 35 AND 39"],
            'age_40_44y'  => ['label' => '40 - 44 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 40 AND 44"],
            'age_45_49y'  => ['label' => '45 - 49 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 45 AND 49"],
            'age_50_54y'  => ['label' => '50 - 54 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 50 AND 54"],
            'age_55_59y'  => ['label' => '55 - 59 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 55 AND 59"],
            'age_60_64y'  => ['label' => '60 - 64 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 60 AND 64"],
            'age_65_69y'  => ['label' => '65 - 69 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 65 AND 69"],
            'age_70_74y'  => ['label' => '70 - 74 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 70 AND 74"],
            'age_75_79y'  => ['label' => '75 - 79 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 75 AND 79"],
            'age_80_84y'  => ['label' => '80 - 84 Tahun',      'condition' => "base.sttsumur = 'Th' AND base.umurdaftar BETWEEN 80 AND 84"],
            'age_gt_85y'   => ['label' => '≥ 85 Tahun',         'condition' => "base.sttsumur = 'Th' AND base.umurdaftar >= 85"],
        ];

        // Building CASE WHEN statements for each age group and gender (FOR KASUS BARU)
        $caseStatements = [];
        foreach ($ageGroups as $key => $group) {
            $caseStatements[] = "SUM(CASE WHEN base.jk = 'L' AND base.is_kasus_baru = 1 AND {$group['condition']} THEN 1 ELSE 0 END) as {$key}_l";
            $caseStatements[] = "SUM(CASE WHEN base.jk = 'P' AND base.is_kasus_baru = 1 AND {$group['condition']} THEN 1 ELSE 0 END) as {$key}_p";
        }

        // Subquery for raw data with "is_kasus_baru" check
        $rawQuery = DB::table('diagnosa_pasien as dp')
            ->join('reg_periksa as reg', 'dp.no_rawat', '=', 'reg.no_rawat')
            ->join('pasien as pas', 'reg.no_rkm_medis', '=', 'pas.no_rkm_medis')
            ->select(
                'dp.kd_penyakit',
                'pas.jk',
                'reg.no_rkm_medis',
                'reg.umurdaftar',
                'reg.sttsumur',
                DB::raw("IF(reg.tgl_registrasi = (
                    SELECT MIN(reg2.tgl_registrasi)
                    FROM diagnosa_pasien dp2
                    JOIN reg_periksa reg2 ON dp2.no_rawat = reg2.no_rawat
                    WHERE dp2.kd_penyakit = dp.kd_penyakit
                    AND reg2.no_rkm_medis = reg.no_rkm_medis
                ), 1, 0) as is_kasus_baru")
            )
            ->where('dp.status', 'Ralan')
            ->where('dp.prioritas', 1)
            ->whereMonth('reg.tgl_registrasi', $month)
            ->whereYear('reg.tgl_registrasi', $year);

        // Main grouping query
        $query = DB::table(DB::raw("({$rawQuery->toSql()}) as base"))
            ->mergeBindings($rawQuery)
            ->join('penyakit as p', 'base.kd_penyakit', '=', 'p.kd_penyakit')
            ->select(
                'base.kd_penyakit',
                'p.nm_penyakit',
                DB::raw(implode(', ', $caseStatements)),
                // Kasus Baru Totals
                DB::raw("SUM(CASE WHEN base.jk = 'L' AND base.is_kasus_baru = 1 THEN 1 ELSE 0 END) as total_l_baru"),
                DB::raw("SUM(CASE WHEN base.jk = 'P' AND base.is_kasus_baru = 1 THEN 1 ELSE 0 END) as total_p_baru"),
                DB::raw("SUM(base.is_kasus_baru) as total_baru"),
                // Kunjungan Totals
                DB::raw("SUM(CASE WHEN base.jk = 'L' THEN 1 ELSE 0 END) as total_l_kunjungan"),
                DB::raw("SUM(CASE WHEN base.jk = 'P' THEN 1 ELSE 0 END) as total_p_kunjungan"),
                DB::raw("COUNT(*) as total_kunjungan")
            )
            ->groupBy('base.kd_penyakit', 'p.nm_penyakit')
            ->orderBy('total_baru', 'desc');

        $data = $query->get();

        return ApiResponse::success('Data Morbiditas Ralan berhasil diambil', [
            'month' => $month,
            'year' => $year,
            'age_groups' => $ageGroups,
            'results' => $data
        ]);
    }

    public function details(Request $request)
    {
        $kd_penyakit = $request->query('kd_penyakit');
        $month = $request->query('month', date('m'));
        $year = $request->query('year', date('Y'));

        if (!$kd_penyakit) {
            return ApiResponse::error('Kode penyakit harus diisi', 400);
        }

        $query = DB::table('diagnosa_pasien as dp')
            ->join('reg_periksa as reg', 'dp.no_rawat', '=', 'reg.no_rawat')
            ->join('pasien as pas', 'reg.no_rkm_medis', '=', 'pas.no_rkm_medis')
            ->join('poliklinik as poli', 'reg.kd_poli', '=', 'poli.kd_poli')
            ->join('dokter as d', 'reg.kd_dokter', '=', 'd.kd_dokter')
            ->select(
                'reg.no_rawat',
                'reg.no_rkm_medis',
                'pas.nm_pasien',
                'pas.jk',
                'reg.tgl_registrasi',
                'reg.jam_reg',
                'poli.nm_poli',
                'd.nm_dokter',
                'reg.stts_daftar',
                'reg.umurdaftar',
                'reg.sttsumur',
                DB::raw("IF(reg.tgl_registrasi = (
                    SELECT MIN(reg2.tgl_registrasi)
                    FROM diagnosa_pasien dp2
                    JOIN reg_periksa reg2 ON dp2.no_rawat = reg2.no_rawat
                    WHERE dp2.kd_penyakit = dp.kd_penyakit
                    AND reg2.no_rkm_medis = reg.no_rkm_medis
                ), 1, 0) as is_kasus_baru")
            )
            ->where('dp.kd_penyakit', $kd_penyakit)
            ->where('dp.status', 'Ralan')
            ->where('dp.prioritas', 1)
            ->whereMonth('reg.tgl_registrasi', $month)
            ->whereYear('reg.tgl_registrasi', $year)
            ->orderBy('reg.tgl_registrasi', 'desc')
            ->orderBy('reg.jam_reg', 'desc');

        $data = $query->get();

        return ApiResponse::success('Detail pasien berhasil diambil', [
            'kd_penyakit' => $kd_penyakit,
            'results' => $data
        ]);
    }
}
