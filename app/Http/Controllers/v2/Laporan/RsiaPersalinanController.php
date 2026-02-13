<?php

namespace App\Http\Controllers\v2\Laporan;

use App\Http\Controllers\Controller;
use App\Models\RawatInapDrPr;
use Illuminate\Http\Request;

class RsiaPersalinanController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->input('limit', 15);
        $start = $request->input('start');
        $end = $request->input('end');
        $kd_pj = $request->input('kd_pj');
        $operator = $request->input('operator');
        $q = $request->input('q');

        $query = RawatInapDrPr::with([
            'jenisPerawatan',
            'regPeriksa.pasien',
            'regPeriksa.caraBayar',
            'regPeriksa.kamarInap' => function($q) {
                $q->with(['kamar.bangsal'])->orderBy('tgl_masuk', 'desc')->orderBy('jam_masuk', 'desc');
            },
            'dokter',
            'petugas'
        ]);

        // Filter for delivery actions (Partus/Persalinan)
        $query->whereHas('jenisPerawatan', function ($sub) {
            $sub->where(function($q) {
                $q->where('nm_perawatan', 'like', '%partus%')
                  ->orWhere('nm_perawatan', 'like', '%persalinan%');
            })->where('nm_perawatan', 'not like', '%konsultasi%');
        });

        if ($request->has('bulan')) {
            $query->whereMonth('tgl_perawatan', $request->bulan);
        }
        if ($request->has('tahun')) {
            $query->whereYear('tgl_perawatan', $request->tahun);
        }

        if ($start && $end) {
            $query->whereBetween('tgl_perawatan', [$start . ' 00:00:00', $end . ' 23:59:59']);
        }

        if ($kd_pj) {
            $query->whereHas('regPeriksa', function ($sub) use ($kd_pj) {
                $sub->where('kd_pj', $kd_pj);
            });
        }

        if ($operator) {
            $query->where('kd_dokter', $operator);
        }

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('no_rawat', 'like', "%{$q}%")
                    ->orWhereHas('regPeriksa.pasien', function ($p) use ($q) {
                        $p->where('nm_pasien', 'like', "%{$q}%")
                          ->orWhere('no_rkm_medis', 'like', "%{$q}%");
                    });
            });
        }

        $data = $query->orderBy('tgl_perawatan', 'desc')
                      ->orderBy('jam_rawat', 'desc')
                      ->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Data Laporan Persalinan (Partus)',
            'data' => $data
        ]);
    }
}
