<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Bangsal;
use App\Models\Kamar;
use App\Models\AplicareKetersediaanKamar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BedAvailabilityController extends Controller
{
    /**
     * Get all bed availability with optional filters
     * Using aplicare_ketersediaan_kamar as primary data source
     */
    public function index(Request $request)
    {
        try {
            $query = AplicareKetersediaanKamar::query()
                ->select([
                    'aplicare_ketersediaan_kamar.*',
                    DB::raw("(SELECT MAX(k.kd_kamar) FROM kamar k WHERE k.kd_bangsal = aplicare_ketersediaan_kamar.kd_bangsal AND k.statusdata = '1') as kd_kamar"),
                    DB::raw("(SELECT status FROM kamar k WHERE k.kd_bangsal = aplicare_ketersediaan_kamar.kd_bangsal AND k.statusdata = '1' ORDER BY k.kd_kamar DESC LIMIT 1) as status_kamar"),
                    // Accurate status counts from kamar table
                    DB::raw("(SELECT COUNT(*) FROM kamar k WHERE k.kd_bangsal = aplicare_ketersediaan_kamar.kd_bangsal AND k.kelas = aplicare_ketersediaan_kamar.kelas AND k.statusdata = '1') as real_kapasitas"),
                    DB::raw("(SELECT COUNT(*) FROM kamar k WHERE k.kd_bangsal = aplicare_ketersediaan_kamar.kd_bangsal AND k.kelas = aplicare_ketersediaan_kamar.kelas AND k.status = 'KOSONG' AND k.statusdata = '1') as real_tersedia"),
                    DB::raw("(SELECT COUNT(*) FROM kamar k WHERE k.kd_bangsal = aplicare_ketersediaan_kamar.kd_bangsal AND k.kelas = aplicare_ketersediaan_kamar.kelas AND k.status = 'ISI' AND k.statusdata = '1') as real_terisi"),
                    DB::raw("(SELECT COUNT(*) FROM kamar k WHERE k.kd_bangsal = aplicare_ketersediaan_kamar.kd_bangsal AND k.kelas = aplicare_ketersediaan_kamar.kelas AND k.status = 'DIBERSIHKAN' AND k.statusdata = '1') as real_dibersihkan"),
                    DB::raw("(SELECT COUNT(*) FROM kamar k WHERE k.kd_bangsal = aplicare_ketersediaan_kamar.kd_bangsal AND k.kelas = aplicare_ketersediaan_kamar.kelas AND k.status = 'DIBOOKING' AND k.statusdata = '1') as real_dibooking")
                ])
                ->with('bangsal');

            // Filter by ward
            if ($request->has('kd_bangsal')) {
                $query->where('kd_bangsal', $request->kd_bangsal);
            }

            // Filter by class
            if ($request->has('kelas')) {
                $query->where('kelas', $request->kelas);
            }

            $availability = $query->orderBy('kd_bangsal')
                                 ->orderBy('kelas')
                                 ->get();

            // Transform data to include calculated fields
            $data = $availability->map(function($item) use ($request) {
                $item_data = [
                    'kode_kelas_aplicare' => $item->kode_kelas_aplicare,
                    'kd_bangsal' => $item->kd_bangsal,
                    'kd_kamar' => $item->kd_kamar ?? $item->kd_bangsal,
                    'nm_bangsal' => $item->bangsal->nm_bangsal ?? $item->kd_bangsal,
                    'kelas' => $item->kelas,
                    'kapasitas' => (int)$item->real_kapasitas,
                    'tersedia' => (int)$item->real_tersedia,
                    'terisi' => (int)$item->real_terisi,
                    'dibersihkan' => (int)$item->real_dibersihkan,
                    'dibooking' => (int)$item->real_dibooking,
                    'status_kamar' => $item->status_kamar,
                    'persentase_terisi' => $item->real_kapasitas > 0 
                        ? round(($item->real_terisi / $item->real_kapasitas) * 100, 1)
                        : 0
                ];
                return $item_data;
            });

            // Filter by status on the calculated fields if requested
            if ($request->has('status') && !empty($request->status)) {
                $status = $request->status;
                $data = $data->filter(function($item) use ($status) {
                    if ($status === 'KOSONG') return $item['tersedia'] > 0;
                    if ($status === 'ISI') return $item['terisi'] > 0;
                    if ($status === 'DIBERSIHKAN') return $item['dibersihkan'] > 0;
                    if ($status === 'DIBOOKING') return $item['dibooking'] > 0;
                    return true;
                })->values();
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $data->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get bed availability summary grouped by ward and class
     */
    public function summary(Request $request)
    {
        try {
            // Get summary from aplicare_ketersediaan_kamar
            $query = AplicareKetersediaanKamar::with('bangsal');

            if ($request->has('kd_bangsal')) {
                $query->where('kd_bangsal', $request->kd_bangsal);
            }

            if ($request->has('kelas')) {
                $query->where('kelas', $request->kelas);
            }

            $summary = $query->get();

            // Also get real-time count from kamar table
            $realTimeStats = Kamar::select(
                'kd_bangsal',
                'kelas',
                'status',
                DB::raw('COUNT(*) as count')
            )
            ->where('statusdata', '<>', '0')
            ->groupBy('kd_bangsal', 'kelas', 'status')
            ->get()
            ->groupBy('kd_bangsal');

            // Get ward information
            $wards = Bangsal::active()->get()->keyBy('kd_bangsal');

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'real_time_stats' => $realTimeStats,
                    'wards' => $wards
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get beds by specific ward
     */
    public function getByWard($kd_bangsal)
    {
        try {
            $ward = Bangsal::with(['kamar' => function($query) {
                              $query->where('statusdata', '<>', '0');
                          }, 'ketersediaanKamar'])
                          ->where('kd_bangsal', $kd_bangsal)
                          ->first();

            if (!$ward) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ward not found'
                ], 404);
            }

            // Get bed statistics
            $stats = [
                'total' => $ward->kamar->count(),
                'available' => $ward->kamar->where('status', 'KOSONG')->count(),
                'occupied' => $ward->kamar->where('status', 'ISI')->count(),
                'cleaning' => $ward->kamar->where('status', 'DIBERSIHKAN')->count(),
                'booked' => $ward->kamar->where('status', 'DIBOOKING')->count(),
            ];

            // Group beds by class
            $bedsByClass = $ward->kamar->groupBy('kelas');

            return response()->json([
                'success' => true,
                'data' => [
                    'ward' => $ward,
                    'stats' => $stats,
                    'beds_by_class' => $bedsByClass,
                    'beds' => $ward->kamar
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all wards with bed counts
     */
    public function getWards()
    {
        try {
            $wards = Bangsal::active()
                           ->withCount(['kamar as total_beds' => function($query) {
                               $query->where('statusdata', '<>', '0');
                           }])
                           ->with(['kamar' => function($query) {
                               $query->select('kd_bangsal', 'status', DB::raw('COUNT(*) as count'))
                                    ->where('statusdata', '<>', '0')
                                    ->groupBy('kd_bangsal', 'status');
                           }])
                           ->get();

            return response()->json([
                'success' => true,
                'data' => $wards
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bed classes
     */
    public function getClasses()
    {
        try {
            $classes = Kamar::select('kelas')
                           ->where('statusdata', '<>', '0')
                           ->distinct()
                           ->whereNotNull('kelas')
                           ->orderBy('kelas')
                           ->pluck('kelas');

            return response()->json([
                'success' => true,
                'data' => $classes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
