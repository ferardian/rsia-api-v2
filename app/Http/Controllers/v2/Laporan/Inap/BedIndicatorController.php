<?php

namespace App\Http\Controllers\v2\Laporan\Inap;

use App\Http\Controllers\Controller;
use App\Models\KamarInap;
use App\Models\RsiaLogJumlahKamar;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BedIndicatorController extends Controller
{
    public function getIndicators(Request $request)
    {
        try {
            $tgl_awal = $request->query('tgl_awal', Carbon::now()->startOfMonth()->toDateString());
            $tgl_akhir = $request->query('tgl_akhir', Carbon::now()->toDateString());

            $start = Carbon::parse($tgl_awal);
            $end = Carbon::parse($tgl_akhir);
            $t = $start->diffInDays($end) + 1;

            // 1. A (Bed Count) - Get from log for the specific month/year
            // If the range spans multiple months, we use the end date's month as the reference for total capacity
            $bedLog = RsiaLogJumlahKamar::where('tahun', $end->format('Y'))
                ->where('bulan', $end->format('m'))
                ->sum('jumlah');

            // Fallback to current bed count if log is empty
            if ($bedLog == 0) {
                $bedLog = DB::table('kamar')->where('statusdata', '1')->count();
            }

            $A = $bedLog;

            // 2. HP (Hari Perawatan) - Total "lama" (days) for patients discharged in this period
            $HP = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
                ->where('stts_pulang', '!=', '-')
                ->sum('lama');

            // 3. D (Pasien Keluar Hidup + Mati)
            $D = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
                ->where('stts_pulang', '!=', '-')
                ->count();

            // 4. Calculations
            $bor = ($A * $t) > 0 ? ($HP / ($A * $t)) * 100 : 0;
            $avlos = $D > 0 ? $HP / $D : 0;
            $toi = $D > 0 ? (($A * $t) - $HP) / $D : 0;
            $bto = $A > 0 ? $D / $A : 0;

            return response()->json([
                'status' => 'success',
                'message' => 'Bed indicators retrieved successfully',
                'data' => [
                    'period' => [
                        'tgl_awal' => $tgl_awal,
                        'tgl_akhir' => $tgl_akhir,
                        'days' => $t,
                    ],
                    'raw_metrics' => [
                        'A' => $A, // Bed count
                        'HP' => (float)$HP, // Hari Perawatan
                        'D' => $D, // Patients discharged
                    ],
                    'indicators' => [
                        'bor' => round($bor, 2),
                        'avlos' => round($avlos, 2),
                        'toi' => round($toi, 2),
                        'bto' => round($bto, 2),
                    ],
                    'ideal_standards' => [
                        'bor' => '60-85%',
                        'avlos' => '6-9 hari',
                        'toi' => '1-3 hari',
                        'bto' => '40-50 kali/tahun (scaled)',
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
