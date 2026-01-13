<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaPermintaanDiet;
use Illuminate\Http\Request;

class PermintaanDietController extends Controller
{
    /**
     * Display a listing of diet requests
     */
    public function index(Request $request)
    {
        try {
            $query = RsiaPermintaanDiet::with('regPeriksa.pasien');

            // Filter by no_rawat
            if ($request->has('no_rawat')) {
                $query->where('no_rawat', $request->no_rawat);
            }

            // Filter by date range
            if ($request->has('tanggal_start')) {
                $query->where('tanggal', '>=', $request->tanggal_start);
            }
            if ($request->has('tanggal_end')) {
                $query->where('tanggal', '<=', $request->tanggal_end);
            }

            $limit = $request->input('limit', 15);
            $data = $query->orderBy('tanggal', 'desc')->paginate($limit);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $th) {
            \Log::error("Error index diet: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified diet request
     */
    public function show(Request $request)
    {
        $request->validate([
            'no_rawat' => 'required|string',
            'tanggal' => 'required|date'
        ]);

        try {
            $diet = RsiaPermintaanDiet::where('no_rawat', $request->no_rawat)
                ->where('tanggal', $request->tanggal)
                ->with('regPeriksa.pasien')
                ->first();

            if (!$diet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data diet tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $diet
            ]);
        } catch (\Throwable $th) {
            \Log::error("Error show diet: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created diet request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'no_rawat' => 'required|string',
            'tanggal' => 'required|date',
            'pagi' => 'nullable|in:Ya,Puasa,Pulang,-',
            'siang' => 'nullable|in:Ya,Puasa,Pulang,-',
            'sore' => 'nullable|in:Ya,Puasa,Pulang,-',
            'permintaan_khusus' => 'nullable|string'
        ]);

        // Set defaults
        $data = array_merge([
            'pagi' => '-',
            'siang' => '-',
            'sore' => '-',
            'permintaan_khusus' => null
        ], $validated);

        try {
            // Check if exists
            $exists = \DB::table('rsia_permintaan_diet')
                ->where('no_rawat', $data['no_rawat'])
                ->where('tanggal', $data['tanggal'])
                ->exists();

            if ($exists) {
                // Update
                \DB::table('rsia_permintaan_diet')
                    ->where('no_rawat', $data['no_rawat'])
                    ->where('tanggal', $data['tanggal'])
                    ->update($data);
            } else {
                // Insert
                \DB::table('rsia_permintaan_diet')->insert($data);
            }

            // Fetch the record
            $diet = RsiaPermintaanDiet::where('no_rawat', $data['no_rawat'])
                ->where('tanggal', $data['tanggal'])
                ->with('regPeriksa.pasien')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Permintaan diet berhasil disimpan',
                'data' => $diet
            ]);
        } catch (\Throwable $th) {
            \Log::error("Error store diet: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan permintaan diet: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified diet request
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'no_rawat' => 'required|string',
            'tanggal' => 'required|date'
        ]);

        try {
            $deleted = \DB::table('rsia_permintaan_diet')
                ->where('no_rawat', $request->no_rawat)
                ->where('tanggal', $request->tanggal)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data diet tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Permintaan diet berhasil dihapus'
            ]);
        } catch (\Throwable $th) {
            \Log::error("Error delete diet: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus permintaan diet: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk store diet requests for multiple patients
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.no_rawat' => 'required|string',
            'items.*.pagi' => 'nullable|in:Ya,Puasa,Pulang,-',
            'items.*.siang' => 'nullable|in:Ya,Puasa,Pulang,-',
            'items.*.sore' => 'nullable|in:Ya,Puasa,Pulang,-',
            'items.*.permintaan_khusus' => 'nullable|string'
        ]);

        $tanggal = $validated['tanggal'];
        $items = $validated['items'];
        
        $successCount = 0;
        $failCount = 0;
        $errors = [];

        try {
            \DB::beginTransaction();

            foreach ($items as $index => $item) {
                try {
                    // Set defaults
                    $data = array_merge([
                        'no_rawat' => $item['no_rawat'],
                        'tanggal' => $tanggal,
                        'pagi' => $item['pagi'] ?? '-',
                        'siang' => $item['siang'] ?? '-',
                        'sore' => $item['sore'] ?? '-',
                        'permintaan_khusus' => $item['permintaan_khusus'] ?? null
                    ]);

                    // Check if exists
                    $exists = \DB::table('rsia_permintaan_diet')
                        ->where('no_rawat', $data['no_rawat'])
                        ->where('tanggal', $data['tanggal'])
                        ->exists();

                    if ($exists) {
                        // Update
                        \DB::table('rsia_permintaan_diet')
                            ->where('no_rawat', $data['no_rawat'])
                            ->where('tanggal', $data['tanggal'])
                            ->update($data);
                    } else {
                        // Insert
                        \DB::table('rsia_permintaan_diet')->insert($data);
                    }

                    $successCount++;
                } catch (\Throwable $e) {
                    $failCount++;
                    $errors[] = [
                        'no_rawat' => $item['no_rawat'],
                        'error' => $e->getMessage()
                    ];
                    \Log::error("Error saving diet for {$item['no_rawat']}: " . $e->getMessage());
                }
            }

            \DB::commit();

            return response()->json([
                'success' => $failCount === 0,
                'message' => "{$successCount} berhasil disimpan" . ($failCount > 0 ? ", {$failCount} gagal" : ""),
                'data' => [
                    'success_count' => $successCount,
                    'fail_count' => $failCount,
                    'errors' => $errors
                ]
            ]);
        } catch (\Throwable $th) {
            \DB::rollBack();
            \Log::error("Error bulk store diet: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan permintaan diet: ' . $th->getMessage()
            ], 500);
        }
    }
}
