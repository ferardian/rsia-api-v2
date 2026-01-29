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

            // Base metrics for overall
            $A_all = $bedLog;
            $HP_all = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
                ->where('stts_pulang', '!=', '-')
                ->sum('lama');
            $D_all = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
                ->where('stts_pulang', '!=', '-')
                ->count();

            // Breakdown by category
            $categories = [
                'Anak' => ['keyword' => 'Anak', 'log_category' => 'Anak'],
                'Kandungan' => ['keyword' => 'Kand', 'log_category' => 'Kandungan'],
                'BYC' => ['keyword' => 'BY', 'log_category' => 'BYC'],
                'ICU' => ['keyword' => 'ICU', 'log_category' => 'ICU'],
                'Isolasi' => ['keyword' => 'ISO', 'log_category' => 'Isolasi'],
            ];

            $breakdown = [];
            foreach ($categories as $key => $cat) {
                // Get bed count for this category from log
                $A = RsiaLogJumlahKamar::where('tahun', $end->format('Y'))
                    ->where('bulan', $end->format('m'))
                    ->where('kategori', $cat['log_category'])
                    ->sum('jumlah');

                // Get HP and D for this category using keyword in kd_kamar
                $HP = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
                    ->where('stts_pulang', '!=', '-')
                    ->where('kd_kamar', 'like', '%' . $cat['keyword'] . '%')
                    ->sum('lama');

                $D = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
                    ->where('stts_pulang', '!=', '-')
                    ->where('kd_kamar', 'like', '%' . $cat['keyword'] . '%')
                    ->count();

                // Calculations for this category
                $bor = ($A * $t) > 0 ? ($HP / ($A * $t)) * 100 : 0;
                $avlos = $D > 0 ? $HP / $D : 0;
                $toi = $D > 0 ? (($A * $t) - $HP) / $D : 0;
                $bto = $A > 0 ? $D / $A : 0;

                $breakdown[] = [
                    'category' => $key,
                    'metrics' => [
                        'A' => (int)$A,
                        'HP' => (float)$HP,
                        'D' => (int)$D,
                    ],
                    'indicators' => [
                        'bor' => round($bor, 2),
                        'avlos' => round($avlos, 2),
                        'toi' => round($toi, 2),
                        'bto' => round($bto, 2),
                    ]
                ];
            }

            // Calculations for overall
            $bor_all = ($A_all * $t) > 0 ? ($HP_all / ($A_all * $t)) * 100 : 0;
            $avlos_all = $D_all > 0 ? $HP_all / $D_all : 0;
            $toi_all = $D_all > 0 ? (($A_all * $t) - $HP_all) / $D_all : 0;
            $bto_all = $A_all > 0 ? $D_all / $A_all : 0;

            return response()->json([
                'status' => 'success',
                'message' => 'Bed indicators retrieved successfully',
                'data' => [
                    'period' => [
                        'tgl_awal' => $tgl_awal,
                        'tgl_akhir' => $tgl_akhir,
                        'days' => $t,
                    ],
                    'overall' => [
                        'raw_metrics' => [
                            'A' => $A_all,
                            'HP' => (float)$HP_all,
                            'D' => $D_all,
                        ],
                        'indicators' => [
                            'bor' => round($bor_all, 2),
                            'avlos' => round($avlos_all, 2),
                            'toi' => round($toi_all, 2),
                            'bto' => round($bto_all, 2),
                        ],
                    ],
                    'breakdown' => $breakdown,
                    'ideal_standards' => [
                        'bor' => '60-85%',
                        'avlos' => '6-9 hari',
                        'toi' => '1-3 hari',
                        'bto' => '40-50 kali/tahun',
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
