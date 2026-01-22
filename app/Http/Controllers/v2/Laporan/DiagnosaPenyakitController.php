<?php

namespace App\Http\Controllers\v2\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiagnosaPenyakitController extends Controller
{
    public function getTop10(Request $request)
    {
        $awal = $request->query('tgl_awal', date('Y-m-d'));
        $akhir = $request->query('tgl_akhir', date('Y-m-d'));
        $status = $request->query('status', 'all'); // Ralan, Ranap, all
        $stts_daftar = $request->query('stts_daftar', 'all'); // Baru, Lama, all
        $jk = $request->query('jk', 'all'); // L, P, all

        $query = DB::table('diagnosa_pasien as dp')
            ->join('penyakit as p', 'dp.kd_penyakit', '=', 'p.kd_penyakit')
            ->join('reg_periksa as reg', 'dp.no_rawat', '=', 'reg.no_rawat')
            ->join('pasien as pas', 'reg.no_rkm_medis', '=', 'pas.no_rkm_medis')
            ->leftJoin('pasien_mati as pm', function($join) {
                $join->on('pas.no_rkm_medis', '=', 'pm.no_rkm_medis')
                     ->whereColumn('pm.tanggal', '>=', 'reg.tgl_registrasi');
            })
            ->select(
                'dp.kd_penyakit', 
                'p.nm_penyakit', 
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(DISTINCT pm.no_rkm_medis) as total_mati')
            )
            ->whereBetween('reg.tgl_registrasi', [$awal, $akhir])
            ->where('reg.stts', '!=', 'Batal');

        // Exclude Z, R, O80, O82
        $query->where(function($q) {
            $q->where('dp.kd_penyakit', 'not like', 'Z%')
              ->where('dp.kd_penyakit', 'not like', 'R%')
              ->where('dp.kd_penyakit', 'not like', 'O80%')
              ->where('dp.kd_penyakit', 'not like', 'O82%');
        });

        if ($status != 'all') {
            $query->where('dp.status', $status);
        }

        if ($stts_daftar != 'all') {
            $query->where('reg.stts_daftar', $stts_daftar);
        }

        if ($jk != 'all') {
            $query->where('pas.jk', $jk);
        }

        $data = $query->groupBy('dp.kd_penyakit', 'p.nm_penyakit')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data 10 Besar Penyakit berhasil diambil',
            'data' => $data,
            'meta' => [
                'tgl_awal' => $awal,
                'tgl_akhir' => $akhir,
                'status' => $status,
                'stts_daftar' => $stts_daftar,
                'jk' => $jk
            ]
        ]);
    }

    public function getSummary(Request $request)
    {
        $awal = $request->query('tgl_awal', date('Y-m-d'));
        $akhir = $request->query('tgl_akhir', date('Y-m-d'));
        $status = $request->query('status', 'all');
        $stts_daftar = $request->query('stts_daftar', 'all');
        $jk = $request->query('jk', 'all');

        $baseQuery = DB::table('diagnosa_pasien as dp')
            ->join('reg_periksa as reg', 'dp.no_rawat', '=', 'reg.no_rawat')
            ->join('pasien as pas', 'reg.no_rkm_medis', '=', 'pas.no_rkm_medis')
            ->whereBetween('reg.tgl_registrasi', [$awal, $akhir])
            ->where('reg.stts', '!=', 'Batal');

        if ($status != 'all') {
            $baseQuery->where('dp.status', $status);
        }

        if ($stts_daftar != 'all') {
            $baseQuery->where('reg.stts_daftar', $stts_daftar);
        }

        if ($jk != 'all') {
            $baseQuery->where('pas.jk', $jk);
        }

        $totalDiagnosa = (clone $baseQuery)->count();
        $uniquePenyakit = (clone $baseQuery)->distinct('kd_penyakit')->count('kd_penyakit');
        
        // Count deaths in this pool of diagnostics
        $totalMati = (clone $baseQuery)
            ->join('pasien_mati as pm', function($join) {
                $join->on('pas.no_rkm_medis', '=', 'pm.no_rkm_medis')
                     ->whereColumn('pm.tanggal', '>=', 'reg.tgl_registrasi');
            })
            ->distinct('pas.no_rkm_medis')
            ->count('pas.no_rkm_medis');

        // Total with exclusions for comparison
        $totalExcluded = (clone $baseQuery)->where(function($q) {
            $q->where('dp.kd_penyakit', 'like', 'Z%')
              ->orWhere('dp.kd_penyakit', 'like', 'R%')
              ->orWhere('dp.kd_penyakit', 'like', 'O80%')
              ->orWhere('dp.kd_penyakit', 'like', 'O82%');
        })->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_diagnosa' => $totalDiagnosa,
                'unique_penyakit' => $uniquePenyakit,
                'total_filtered' => $totalDiagnosa - $totalExcluded,
                'total_mati' => $totalMati
            ]
        ]);
    }
}
