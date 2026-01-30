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

            $data = $this->calculateMetrics($tgl_awal, $tgl_akhir);

            return response()->json([
                'status' => 'success',
                'message' => 'Bed indicators retrieved successfully',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getYearlyIndicators(Request $request)
    {
        try {
            $tahun = $request->query('tahun', Carbon::now()->year);
            $kategori = $request->query('kategori', 'Gabungan');
            
            $monthlyData = [];
            for ($m = 1; $m <= 12; $m++) {
                $startOfMonth = Carbon::create($tahun, $m, 1)->startOfMonth()->toDateString();
                $endOfMonth = Carbon::create($tahun, $m, 1)->endOfMonth()->toDateString();
                
                // If it's the current year and future month, stop or return 0
                if ($tahun == Carbon::now()->year && $m > Carbon::now()->month) {
                    $monthlyData[] = [
                        'month' => $m,
                        'month_name' => Carbon::create($tahun, $m, 1)->format('F'),
                        'bor' => 0,
                        'avlos' => 0,
                        'toi' => 0,
                        'bto' => 0,
                    ];
                    continue;
                }

                $metrics = $this->calculateMetrics($startOfMonth, $endOfMonth);
                
                $indicatorTarget = null;
                if ($kategori === 'Gabungan') {
                    $indicatorTarget = $metrics['overall']['indicators'];
                } else {
                    $foundBreakdown = collect($metrics['breakdown'])->firstWhere('category', $kategori);
                    $indicatorTarget = $foundBreakdown ? $foundBreakdown['indicators'] : null;
                }

                $monthlyData[] = [
                    'month' => $m,
                    'month_name' => Carbon::create($tahun, $m, 1)->format('F'),
                    'bor' => $indicatorTarget ? $indicatorTarget['bor'] : 0,
                    'avlos' => $indicatorTarget ? $indicatorTarget['avlos'] : 0,
                    'toi' => $indicatorTarget ? $indicatorTarget['toi'] : 0,
                    'bto' => $indicatorTarget ? $indicatorTarget['bto'] : 0,
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Yearly bed indicators retrieved successfully',
                'data' => [
                    'tahun' => $tahun,
                    'kategori' => $kategori,
                    'months' => $monthlyData
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function calculateMetrics($tgl_awal, $tgl_akhir)
    {
        $start = Carbon::parse($tgl_awal);
        $end = Carbon::parse($tgl_akhir);
        $t = $start->diffInDays($end) + 1;

        $tahun = $end->format('Y');
        $bulan = $end->month;

        $bedLog = RsiaLogJumlahKamar::where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->sum('jumlah');

        if ($bedLog == 0) {
            $bedLog = DB::table('kamar')->where('statusdata', '1')->count();
        }

        $A_all = $bedLog;
        $HP_all = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
            ->where('tgl_keluar', '!=', '0000-00-00')
            ->where('kd_kamar', 'not like', '%BYA%')
            ->sum('lama');
        $D_all = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
            ->where('tgl_keluar', '!=', '0000-00-00')
            ->where('stts_pulang', '!=', 'Pindah Kamar')
            ->where('kd_kamar', 'not like', '%BYA%')
            ->distinct()
            ->count('no_rawat');

        $categories = [
            'Anak' => ['keyword' => 'Anak', 'log_category' => 'Anak'],
            'Kandungan' => ['keyword' => 'Kand', 'log_category' => 'Kandungan'],
            'BYC' => ['keyword' => 'BY', 'log_category' => 'BYC'],
            'ICU' => ['keyword' => 'ICU', 'log_category' => 'ICU'],
            'Isolasi' => ['keyword' => 'ISO', 'log_category' => 'Isolasi'],
        ];

        $breakdown = [];
        foreach ($categories as $key => $cat) {
            $A = RsiaLogJumlahKamar::where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->where('kategori', $cat['log_category'])
                ->sum('jumlah');

            if ($A == 0) {
                $A = DB::table('kamar')
                    ->where('statusdata', '1')
                    ->where('kd_kamar', 'like', '%' . $cat['keyword'] . '%')
                    ->count();
            }

            $HP = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
                ->where('tgl_keluar', '!=', '0000-00-00')
                ->where('kd_kamar', 'like', '%' . $cat['keyword'] . '%')
                ->sum('lama');

            $D = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
                ->where('tgl_keluar', '!=', '0000-00-00')
                ->where('stts_pulang', '!=', 'Pindah Kamar')
                ->where('kd_kamar', 'like', '%' . $cat['keyword'] . '%')
                ->distinct()
                ->count('no_rawat');

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

        $bor_all = ($A_all * $t) > 0 ? ($HP_all / ($A_all * $t)) * 100 : 0;
        $avlos_all = $D_all > 0 ? $HP_all / $D_all : 0;
        $toi_all = $D_all > 0 ? (($A_all * $t) - $HP_all) / $D_all : 0;
        $bto_all = $A_all > 0 ? $D_all / $A_all : 0;

        return [
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
        ];
    }
}
