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
                $rawMetricsTarget = null;
                
                if ($kategori === 'Gabungan') {
                    $indicatorTarget = $metrics['overall']['indicators'];
                    $rawMetricsTarget = $metrics['overall']['raw_metrics'];
                } else {
                    $foundBreakdown = collect($metrics['breakdown'])->firstWhere('category', $kategori);
                    $indicatorTarget = $foundBreakdown ? $foundBreakdown['indicators'] : null;
                    $rawMetricsTarget = $foundBreakdown ? $foundBreakdown['metrics'] : null;
                }

                $monthlyData[] = [
                    'month' => $m,
                    'month_name' => Carbon::create($tahun, $m, 1)->format('F'),
                    'bor' => $indicatorTarget ? $indicatorTarget['bor'] : 0,
                    'avlos' => $indicatorTarget ? $indicatorTarget['avlos'] : 0,
                    'toi' => $indicatorTarget ? $indicatorTarget['toi'] : 0,
                    'bto' => $indicatorTarget ? $indicatorTarget['bto'] : 0,
                    'A' => $rawMetricsTarget ? $rawMetricsTarget['A'] : 0,
                    'HP' => $rawMetricsTarget ? $rawMetricsTarget['HP'] : 0,
                    'D' => $rawMetricsTarget ? $rawMetricsTarget['D'] : 0,
                ];
            }

            // Calculate yearly summary
            $startOfYear = Carbon::create($tahun, 1, 1)->startOfYear()->toDateString();
            $endOfYear = Carbon::create($tahun, 12, 31)->endOfYear()->toDateString();
            
            // If it's current year, end date is today
            if ($tahun == Carbon::now()->year) {
                $endOfYear = Carbon::now()->toDateString();
            }

            $yearlyMetrics = $this->calculateMetrics($startOfYear, $endOfYear);
            
            $aggregateTarget = null;
            if ($kategori === 'Gabungan') {
                $aggregateTarget = $yearlyMetrics['overall'];
            } else {
                $foundBreakdown = collect($yearlyMetrics['breakdown'])->firstWhere('category', $kategori);
                if ($foundBreakdown) {
                    $aggregateTarget = [
                        'raw_metrics' => $foundBreakdown['metrics'],
                        'indicators' => $foundBreakdown['indicators'],
                        'ward_occupancy' => $foundBreakdown['ward_occupancy']
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Yearly bed indicators retrieved successfully',
                'data' => [
                    'tahun' => $tahun,
                    'kategori' => $kategori,
                    'months' => $monthlyData,
                    'overall' => $aggregateTarget
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

        // Detect if this is a multi-month period (yearly calculation)
        $isMultiMonth = $start->format('Y-m') !== $end->format('Y-m');
        
        if ($isMultiMonth) {
            // Weighted average calculation for multi-month periods
            $totalBedDays = 0;
            $currentDate = $start->copy()->startOfMonth();
            
            while ($currentDate <= $end) {
                $monthStart = max($start, $currentDate->copy()->startOfMonth());
                $monthEnd = min($end, $currentDate->copy()->endOfMonth());
                $daysInPeriod = $monthStart->diffInDays($monthEnd) + 1;
                
                $monthBedLog = RsiaLogJumlahKamar::where('tahun', $currentDate->year)
                    ->where('bulan', $currentDate->month)
                    ->sum('jumlah');
                
                if ($monthBedLog == 0) {
                    // Fallback to current table count
                    $monthBedLog = DB::table('kamar')
                        ->where('statusdata', '1')
                        ->where('kd_kamar', 'not like', '%BY_')
                        ->where('kd_kamar', 'not like', '%BYA%')
                        ->where('kd_kamar', 'not like', '%RG%')
                        ->count();
                }
                
                $totalBedDays += $monthBedLog * $daysInPeriod;
                $currentDate->addMonth();
            }
            
            // For BOR calculation, we'll use totalBedDays directly
            // A_all represents average beds, but we'll store totalBedDays for accurate calculation
            $A_all = $totalBedDays / $t; // Average bed count
            $bedDaysForCalculation = $totalBedDays;
        } else {
            // Single month calculation (existing logic)
            $bedLog = RsiaLogJumlahKamar::where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->sum('jumlah');

            if ($bedLog == 0) {
                $bedLog = DB::table('kamar')
                    ->where('statusdata', '1')
                    ->where('kd_kamar', 'not like', '%BY_')
                    ->where('kd_kamar', 'not like', '%BYA%')
                    ->where('kd_kamar', 'not like', '%RG%')
                    ->count();
            }
            
            $A_all = $bedLog;
            $bedDaysForCalculation = $A_all * $t;
        }

        $HP_all = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
            ->where('tgl_keluar', '!=', '0000-00-00')
            ->where('kd_kamar', 'not like', '%BYA%')
            ->where('kd_kamar', 'not like', '%RG%')
            ->sum('lama');
        $D_all = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
            ->where('tgl_keluar', '!=', '0000-00-00')
            ->where('stts_pulang', '!=', 'Pindah Kamar')
            ->where('kd_kamar', 'not like', '%BYA%')
            ->where('kd_kamar', 'not like', '%RG%')
            ->distinct()
            ->count('no_rawat');

        $categories = [
            'Anak' => ['keyword' => 'Anak', 'log_category' => 'Anak'],
            'Kandungan' => ['keyword' => 'Kand', 'log_category' => 'Kandungan'],
            'BYC' => ['keyword' => 'BY', 'log_category' => 'BYC'],
            'ICU' => ['keyword' => 'ICU', 'log_category' => 'ICU'],
            'Isolasi' => ['keyword' => 'ISO', 'log_category' => 'Isolasi'],
            'Umum' => ['keyword' => 'UMUM_NON_INTENSIF', 'log_category' => null],
        ];


        $breakdown = [];
        foreach ($categories as $key => $cat) {
            // Calculate bed count (A) with weighted average for multi-month periods
            if ($isMultiMonth) {
                $totalCategoryBedDays = 0;
                $currentDate = $start->copy()->startOfMonth();
                
                while ($currentDate <= $end) {
                    $monthStart = max($start, $currentDate->copy()->startOfMonth());
                    $monthEnd = min($end, $currentDate->copy()->endOfMonth());
                    $daysInPeriod = $monthStart->diffInDays($monthEnd) + 1;
                    
                    if ($key === 'Umum') {
                        $totalLog = RsiaLogJumlahKamar::where('tahun', $currentDate->year)
                            ->where('bulan', $currentDate->month)
                            ->sum('jumlah');
                        $icuLog = RsiaLogJumlahKamar::where('tahun', $currentDate->year)
                            ->where('bulan', $currentDate->month)
                            ->where('kategori', 'ICU')
                            ->sum('jumlah');
                        $bycLog = RsiaLogJumlahKamar::where('tahun', $currentDate->year)
                            ->where('bulan', $currentDate->month)
                            ->where('kategori', 'BYC')
                            ->sum('jumlah');
                        $monthA = $totalLog - $icuLog - $bycLog;
                    } else {
                        $monthA = RsiaLogJumlahKamar::where('tahun', $currentDate->year)
                            ->where('bulan', $currentDate->month)
                            ->where('kategori', $cat['log_category'])
                            ->sum('jumlah');
                    }
                    
                    if ($monthA == 0) {
                        $rooms = DB::table('kamar')->where('statusdata', '1');
                        if ($key === 'ICU') {
                            $rooms->where(function ($q) {
                                $q->where('kd_kamar', 'like', '%ICU%')
                                    ->orWhere('kd_kamar', 'like', '%NICU%')
                                    ->orWhere('kd_kamar', 'like', '%PICU%');
                            });
                        } elseif ($key === 'Umum') {
                            $rooms->where('kd_kamar', 'not like', '%ICU%')
                                ->where('kd_kamar', 'not like', '%NICU%')
                                ->where('kd_kamar', 'not like', '%PICU%')
                                ->where('kd_kamar', 'not like', '%BY%')
                                ->where('kd_kamar', 'not like', '%BYA%')
                                ->where('kd_kamar', 'not like', '%RG%');
                        } else {
                            $rooms->where('kd_kamar', 'like', '%' . $cat['keyword'] . '%');
                            if ($key !== 'BYC') {
                                $rooms->where('kd_kamar', 'not like', '%BYA%')
                                    ->where('kd_kamar', 'not like', '%RG%');
                            }
                        }
                        $monthA = $rooms->count();
                    }
                    
                    $totalCategoryBedDays += $monthA * $daysInPeriod;
                    $currentDate->addMonth();
                }
                
                $A = $totalCategoryBedDays / $t; // Average beds for display
                $categoryBedDays = $totalCategoryBedDays;
            } else {
                // Single month calculation (existing logic)
                if ($key === 'Umum') {
                    $totalLog = RsiaLogJumlahKamar::where('tahun', $tahun)
                        ->where('bulan', $bulan)
                        ->sum('jumlah');
                    $icuLog = RsiaLogJumlahKamar::where('tahun', $tahun)
                        ->where('bulan', $bulan)
                        ->where('kategori', 'ICU')
                        ->sum('jumlah');
                    $bycLog = RsiaLogJumlahKamar::where('tahun', $tahun)
                        ->where('bulan', $bulan)
                        ->where('kategori', 'BYC')
                        ->sum('jumlah');
                    $A = $totalLog - $icuLog - $bycLog;
                } else {
                    $A = RsiaLogJumlahKamar::where('tahun', $tahun)
                        ->where('bulan', $bulan)
                        ->where('kategori', $cat['log_category'])
                        ->sum('jumlah');
                }

                if ($A == 0) {
                    $rooms = DB::table('kamar')->where('statusdata', '1');
                    if ($key === 'ICU') {
                        $rooms->where(function ($q) {
                            $q->where('kd_kamar', 'like', '%ICU%')
                                ->orWhere('kd_kamar', 'like', '%NICU%')
                                ->orWhere('kd_kamar', 'like', '%PICU%');
                        });
                    } elseif ($key === 'Umum') {
                        $rooms->where('kd_kamar', 'not like', '%ICU%')
                            ->where('kd_kamar', 'not like', '%NICU%')
                            ->where('kd_kamar', 'not like', '%PICU%')
                            ->where('kd_kamar', 'not like', '%BY%')
                            ->where('kd_kamar', 'not like', '%BYA%')
                            ->where('kd_kamar', 'not like', '%RG%');
                    } else {
                        $rooms->where('kd_kamar', 'like', '%' . $cat['keyword'] . '%');
                        if ($key !== 'BYC') {
                            $rooms->where('kd_kamar', 'not like', '%BYA%')
                                ->where('kd_kamar', 'not like', '%RG%');
                        }
                    }
                    $A = $rooms->count();
                }
                
                $categoryBedDays = $A * $t;
            }

            $hpQuery = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
                ->where('tgl_keluar', '!=', '0000-00-00');

            if ($key === 'ICU') {
                $hpQuery->where(function ($q) {
                    $q->where('kd_kamar', 'like', '%ICU%')
                        ->orWhere('kd_kamar', 'like', '%NICU%')
                        ->orWhere('kd_kamar', 'like', '%PICU%');
                });
            } elseif ($key === 'Umum') {
                $hpQuery->where('kd_kamar', 'not like', '%ICU%')
                    ->where('kd_kamar', 'not like', '%NICU%')
                    ->where('kd_kamar', 'not like', '%PICU%')
                    ->where('kd_kamar', 'not like', '%BY%')
                    ->where('kd_kamar', 'not like', '%BYA%')
                    ->where('kd_kamar', 'not like', '%RG%');
            } else {
                $hpQuery->where('kd_kamar', 'like', '%' . $cat['keyword'] . '%');
            }
            $HP = $hpQuery->sum('lama');

            $dQuery = KamarInap::whereBetween('tgl_keluar', [$tgl_awal, $tgl_akhir])
                ->where('tgl_keluar', '!=', '0000-00-00')
                ->where('stts_pulang', '!=', 'Pindah Kamar');

            if ($key === 'ICU') {
                $dQuery->where(function ($q) {
                    $q->where('kd_kamar', 'like', '%ICU%')
                        ->orWhere('kd_kamar', 'like', '%NICU%')
                        ->orWhere('kd_kamar', 'like', '%PICU%');
                });
            } elseif ($key === 'Umum') {
                $dQuery->where('kd_kamar', 'not like', '%ICU%')
                    ->where('kd_kamar', 'not like', '%NICU%')
                    ->where('kd_kamar', 'not like', '%PICU%')
                    ->where('kd_kamar', 'not like', '%BY%')
                    ->where('kd_kamar', 'not like', '%BYA%')
                    ->where('kd_kamar', 'not like', '%RG%');
            } else {
                $dQuery->where('kd_kamar', 'like', '%' . $cat['keyword'] . '%');
            }
            $D = $dQuery->distinct()->count('no_rawat');

            $bor = $categoryBedDays > 0 ? ($HP / $categoryBedDays) * 100 : 0;
            $avlos = $D > 0 ? $HP / $D : 0;
            $toi = $D > 0 ? ($categoryBedDays - $HP) / $D : 0;
            $bto = $A > 0 ? $D / $A : 0;

            $breakdown[] = [
                'category' => $key,
                'metrics' => [
                    'A' => (int)round($A),
                    'HP' => (float)$HP,
                    'D' => (int)$D,
                ],
                'indicators' => [
                    'bor' => round($bor, 2),
                    'avlos' => round($avlos, 2),
                    'toi' => round($toi, 2),
                    'bto' => round($bto, 2),
                ],
                'ward_occupancy' => $this->getWardOccupancy($tgl_awal, $tgl_akhir, $t, $key === 'ICU' ? 'ICU_PICU_NICU' : ($key === 'Umum' ? 'UMUM_NON_INTENSIF' : $cat['keyword']))
            ];
        }

        // 11. BOR Per Kelas
        $classes = ['Kelas 1', 'Kelas 2', 'Kelas 3', 'Kelas Utama', 'Kelas VIP', 'Kelas VVIP'];
        $borPerKelas = [];
        
        foreach ($classes as $cl) {
            $A_cl = DB::table('kamar')
                ->where('statusdata', '1')
                ->where('kelas', $cl)
                ->where('kd_kamar', 'not like', '%BYA%')
                ->where('kd_kamar', 'not like', '%RG%')
                ->count();
                
            $HP_cl = KamarInap::join('kamar', 'kamar_inap.kd_kamar', '=', 'kamar.kd_kamar')
                ->whereBetween('kamar_inap.tgl_keluar', [$tgl_awal, $tgl_akhir])
                ->where('kamar_inap.tgl_keluar', '!=', '0000-00-00')
                ->where('kamar.kelas', $cl)
                ->where('kamar.kd_kamar', 'not like', '%BYA%')
                ->where('kamar.kd_kamar', 'not like', '%RG%')
                ->sum('kamar_inap.lama');
                
            $bor_cl = ($A_cl * $t) > 0 ? ($HP_cl / ($A_cl * $t)) * 100 : 0;
            
            $borPerKelas[] = [
                'kelas' => $cl,
                'bor' => round($bor_cl, 2),
                'A' => $A_cl,
                'HP' => $HP_cl
            ];
        }


        $bor_all = $bedDaysForCalculation > 0 ? ($HP_all / $bedDaysForCalculation) * 100 : 0;
        $avlos_all = $D_all > 0 ? $HP_all / $D_all : 0;
        $toi_all = $D_all > 0 ? ($bedDaysForCalculation - $HP_all) / $D_all : 0;
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
                'bor_per_kelas' => $borPerKelas,
                'ward_occupancy' => $this->getWardOccupancy($tgl_awal, $tgl_akhir, $t)
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

    private function getWardOccupancy($tgl_awal, $tgl_akhir, $t, $keyword = null)
    {
        // 1. Get Room Capacity (A) per Ward
        $rooms = DB::table('kamar')
            ->join('bangsal', 'kamar.kd_bangsal', '=', 'bangsal.kd_bangsal')
            ->where('kamar.statusdata', '1');
            
        if ($keyword === 'ICU_PICU_NICU') {
            $rooms->where(function ($q) {
                $q->where('kamar.kd_kamar', 'like', '%ICU%')
                    ->orWhere('kamar.kd_kamar', 'like', '%NICU%')
                    ->orWhere('kamar.kd_kamar', 'like', '%PICU%');
            });
        } elseif ($keyword === 'UMUM_NON_INTENSIF') {
            $rooms->where('kamar.kd_kamar', 'not like', '%ICU%')
                ->where('kamar.kd_kamar', 'not like', '%NICU%')
                ->where('kamar.kd_kamar', 'not like', '%PICU%')
                ->where('kamar.kd_kamar', 'not like', '%BY%')
                ->where('kamar.kd_kamar', 'not like', '%BYA%')
                ->where('kamar.kd_kamar', 'not like', '%RG%');
        } elseif ($keyword) {
            $rooms->where('kamar.kd_kamar', 'like', '%' . $keyword . '%');
        } else {
            // Overall: exclude baby rooms like the main logic
            $rooms->where('kamar.kd_kamar', 'not like', '%BYA%')
                ->where('kamar.kd_kamar', 'not like', '%RG%');
        }
        
        $roomData = $rooms->select('bangsal.nm_bangsal', DB::raw('COUNT(*) as total_bed'))
            ->groupBy('bangsal.nm_bangsal')
            ->get();

        // 2. Get Days of Care (HP) per Ward
        $careDays = KamarInap::join('kamar', 'kamar_inap.kd_kamar', '=', 'kamar.kd_kamar')
            ->join('bangsal', 'kamar.kd_bangsal', '=', 'bangsal.kd_bangsal')
            ->whereBetween('kamar_inap.tgl_keluar', [$tgl_awal, $tgl_akhir])
            ->where('kamar_inap.tgl_keluar', '!=', '0000-00-00');
            
        if ($keyword === 'ICU_PICU_NICU') {
            $careDays->where(function ($q) {
                $q->where('kamar.kd_kamar', 'like', '%ICU%')
                    ->orWhere('kamar.kd_kamar', 'like', '%NICU%')
                    ->orWhere('kamar.kd_kamar', 'like', '%PICU%');
            });
        } elseif ($keyword === 'UMUM_NON_INTENSIF') {
            $careDays->where('kamar.kd_kamar', 'not like', '%ICU%')
                ->where('kamar.kd_kamar', 'not like', '%NICU%')
                ->where('kamar.kd_kamar', 'not like', '%PICU%')
                ->where('kamar.kd_kamar', 'not like', '%BY%')
                ->where('kamar.kd_kamar', 'not like', '%BYA%')
                ->where('kamar.kd_kamar', 'not like', '%RG%');
        } elseif ($keyword) {
            $careDays->where('kamar.kd_kamar', 'like', '%' . $keyword . '%');
        } else {
            $careDays->where('kamar.kd_kamar', 'not like', '%BYA%')
                ->where('kamar.kd_kamar', 'not like', '%RG%');
        }
        
        $careData = $careDays->select('bangsal.nm_bangsal', DB::raw('SUM(kamar_inap.lama) as total_hp'))
            ->groupBy('bangsal.nm_bangsal')
            ->get();

        // 3. Grouping into main wards (Grouping similar wards)
        $grouped = [];
        foreach ($roomData as $rd) {
            $normalized = $this->normalizeWardName($rd->nm_bangsal);
            if (!isset($grouped[$normalized])) {
                $grouped[$normalized] = ['label' => $normalized, 'A' => 0, 'HP' => 0];
            }
            $grouped[$normalized]['A'] += $rd->total_bed;
        }

        foreach ($careData as $cd) {
            $normalized = $this->normalizeWardName($cd->nm_bangsal);
            if (isset($grouped[$normalized])) {
                $grouped[$normalized]['HP'] += (float)$cd->total_hp;
            }
        }

        $result = [];
        foreach ($grouped as $g) {
            $bor = ($g['A'] * $t) > 0 ? ($g['HP'] / ($g['A'] * $t)) * 100 : 0;
            $result[] = [
                'label' => $g['label'],
                'bor' => round($bor, 2),
                'A' => $g['A'],
                'HP' => $g['HP']
            ];
        }

        usort($result, fn($a, $b) => $b['bor'] <=> $a['bor']);
        return $result;
    }

    private function normalizeWardName($name)
    {
        // 1. Specific Keyword Mapping (for units with sub-units)
        // ICU, PICU, NICU -> ICU
        if (preg_match('/(ICU|PICU|NICU)/i', $name)) {
            return 'ICU';
        }

        // ISOLASI -> ISOLASI (grouping all isolation/iso rooms)
        if (stripos($name, 'ISOLASI') !== false || stripos($name, 'ISO') !== false) {
            return 'ISOLASI';
        }

        // SITI KHADIJAH -> SITI KHADIJAH (excluding BAYI)
        if (stripos($name, 'SITI KHADIJAH') !== false && stripos($name, 'BAYI') === false) {
            return 'SITI KHADIJAH';
        }

        // SITI FATIMAH -> SITI FATIMAH (grouping AZZAHRA etc)
        if (stripos($name, 'SITI FATIMAH') !== false) {
            return 'SITI FATIMAH';
        }

        // SITI WALIDAH -> SITI WALIDAH
        if (stripos($name, 'SITI WALIDAH') !== false) {
            return 'SITI WALIDAH';
        }

        // 2. Generic Normalization (trailing numbers and parentheses)
        // Remove trailing numbers, dots, and parenthesized info
        // Example: "KHADIJAH 1" -> "KHADIJAH"
        // "SITI AISYAH VIP B1" -> "SITI AISYAH VIP B"
        $normalized = preg_replace('/\s*\(.*\)$/', '', $name);
        $normalized = preg_replace('/\s*[0-9]+(\.[0-9]+)?$/', '', $normalized);
        $normalized = preg_replace('/\s*[0-9]+$/', '', $normalized);
        
        return trim($normalized);
    }
}
