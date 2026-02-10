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

        // Define age groups for SIRS/RL reporting
        $ageGroups = [
            'age_lt_1h'    => ['label' => '< 1 Jam',            'condition' => 'diff_hours < 1'],
            'age_1_23h'   => ['label' => '1 - 23 Jam',         'condition' => 'diff_hours >= 1 AND diff_hours < 24'],
            'age_1_7d'    => ['label' => '1 - 7 Hari',         'condition' => 'diff_days >= 1 AND diff_days < 8'],
            'age_8_28d'   => ['label' => '8 - 28 Hari',        'condition' => 'diff_days >= 8 AND diff_days <= 28'],
            'age_29d_3m'  => ['label' => '29 Hari - <3 Bulan', 'condition' => 'diff_months >= 1 AND diff_months < 3'],
            'age_3_6m'    => ['label' => '3 - <6 Bulan',       'condition' => 'diff_months >= 3 AND diff_months < 6'],
            'age_6_11m'   => ['label' => '6 - 11 Bulan',       'condition' => 'diff_months >= 6 AND diff_months < 12'],
            'age_1_4y'    => ['label' => '1 - 4 Tahun',        'condition' => 'diff_years >= 1 AND diff_years <= 4'],
            'age_5_9y'    => ['label' => '5 - 9 Tahun',        'condition' => 'diff_years >= 5 AND diff_years <= 9'],
            'age_10_14y'  => ['label' => '10 - 14 Tahun',      'condition' => 'diff_years >= 10 AND diff_years <= 14'],
            'age_15_19y'  => ['label' => '15 - 19 Tahun',      'condition' => 'diff_years >= 15 AND diff_years <= 19'],
            'age_20_24y'  => ['label' => '20 - 24 Tahun',      'condition' => 'diff_years >= 20 AND diff_years <= 24'],
            'age_25_29y'  => ['label' => '25 - 29 Tahun',      'condition' => 'diff_years >= 25 AND diff_years <= 29'],
            'age_30_34y'  => ['label' => '30 - 34 Tahun',      'condition' => 'diff_years >= 30 AND diff_years <= 34'],
            'age_35_39y'  => ['label' => '35 - 39 Tahun',      'condition' => 'diff_years >= 35 AND diff_years <= 39'],
            'age_40_44y'  => ['label' => '40 - 44 Tahun',      'condition' => 'diff_years >= 40 AND diff_years <= 44'],
            'age_45_49y'  => ['label' => '45 - 49 Tahun',      'condition' => 'diff_years >= 45 AND diff_years <= 49'],
            'age_50_54y'  => ['label' => '50 - 54 Tahun',      'condition' => 'diff_years >= 50 AND diff_years <= 54'],
            'age_55_59y'  => ['label' => '55 - 59 Tahun',      'condition' => 'diff_years >= 55 AND diff_years <= 59'],
            'age_60_64y'  => ['label' => '60 - 64 Tahun',      'condition' => 'diff_years >= 60 AND diff_years <= 64'],
            'age_65_69y'  => ['label' => '65 - 69 Tahun',      'condition' => 'diff_years >= 65 AND diff_years <= 69'],
            'age_70_74y'  => ['label' => '70 - 74 Tahun',      'condition' => 'diff_years >= 70 AND diff_years <= 74'],
            'age_75_79y'  => ['label' => '75 - 79 Tahun',      'condition' => 'diff_years >= 75 AND diff_years <= 79'],
            'age_80_84y'  => ['label' => '80 - 84 Tahun',      'condition' => 'diff_years >= 80 AND diff_years <= 84'],
            'age_gt_85y'   => ['label' => '≥ 85 Tahun',         'condition' => 'diff_years >= 85'],
        ];

        // Building CASE WHEN statements for each age group and gender (FOR NEW CASES)
        $caseStatements = [];
        foreach ($ageGroups as $key => $group) {
            $caseStatements[] = "SUM(CASE WHEN base.jk = 'L' AND base.stts_daftar = 'Baru' AND base.{$group['condition']} THEN 1 ELSE 0 END) as {$key}_l";
            $caseStatements[] = "SUM(CASE WHEN base.jk = 'P' AND base.stts_daftar = 'Baru' AND base.{$group['condition']} THEN 1 ELSE 0 END) as {$key}_p";
        }

        // Subquery for raw data with calculated diffs
        $rawQuery = DB::table('diagnosa_pasien as dp')
            ->join('reg_periksa as reg', 'dp.no_rawat', '=', 'reg.no_rawat')
            ->join('pasien as pas', 'reg.no_rkm_medis', '=', 'pas.no_rkm_medis')
            ->select(
                'dp.kd_penyakit',
                'pas.jk',
                'reg.stts_daftar',
                DB::raw("TIMESTAMPDIFF(HOUR, pas.tgl_lahir, reg.tgl_registrasi) as diff_hours"),
                DB::raw("DATEDIFF(reg.tgl_registrasi, pas.tgl_lahir) as diff_days"),
                DB::raw("PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM reg.tgl_registrasi), EXTRACT(YEAR_MONTH FROM pas.tgl_lahir)) as diff_months"),
                DB::raw("TIMESTAMPDIFF(YEAR, pas.tgl_lahir, reg.tgl_registrasi) as diff_years")
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
                DB::raw("SUM(CASE WHEN base.jk = 'L' AND base.stts_daftar = 'Baru' THEN 1 ELSE 0 END) as total_l_baru"),
                DB::raw("SUM(CASE WHEN base.jk = 'P' AND base.stts_daftar = 'Baru' THEN 1 ELSE 0 END) as total_p_baru"),
                DB::raw("SUM(CASE WHEN base.stts_daftar = 'Baru' THEN 1 ELSE 0 END) as total_baru"),
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
}
