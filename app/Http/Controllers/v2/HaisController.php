<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\Hais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HaisController extends Controller
{
    /**
     * Display a listing of HAIS records.
     */
    public function index(Request $request)
    {
        try {
            $query = Hais::query();

            if ($request->has('no_rawat')) {
                $query->where('no_rawat', $request->no_rawat);
            }

            if ($request->has('tanggal_awal') && $request->has('tanggal_akhir')) {
                $query->whereBetween('tanggal', [$request->tanggal_awal, $request->tanggal_akhir]);
            }

            $query->orderBy('tanggal', 'desc');
            
            $data = $query->get();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $th) {
            Log::error("Error index HAIS: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data HAIS: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created HAIS record.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'no_rawat' => 'required|string|exists:reg_periksa,no_rawat',
            'ett' => 'nullable|integer',
            'cvl' => 'nullable|integer',
            'ivl' => 'nullable|integer',
            'uc' => 'nullable|integer',
            'op' => 'nullable|integer',
            'vap' => 'nullable|integer',
            'iad' => 'nullable|integer',
            'pleb' => 'nullable|integer',
            'isk' => 'nullable|integer',
            'ido' => 'nullable|integer',
            'hap' => 'nullable|integer',
            'tinea' => 'nullable|integer',
            'scabies' => 'nullable|integer',
            'deku' => 'nullable|in:YA,TIDAK',
            'antibiotik' => 'nullable|string|max:200',
            'kategori' => 'nullable|string|max:100',
        ]);

        try {
            // Check if record for this date and no_rawat already exists
            $exists = Hais::where('tanggal', $validated['tanggal'])
                ->where('no_rawat', $validated['no_rawat'])
                ->first();

            if ($exists) {
                $exists->update($validated);
                $data = $exists;
                $message = 'Data HAIS berhasil diperbarui';
            } else {
                $data = Hais::create($validated);
                $message = 'Data HAIS berhasil disimpan';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data
            ]);
        } catch (\Throwable $th) {
            Log::error("Error store HAIS: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data HAIS: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified HAIS record.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'tanggal' => 'sometimes|required|date',
            'no_rawat' => 'sometimes|required|string|exists:reg_periksa,no_rawat',
            'ett' => 'nullable|integer',
            'cvl' => 'nullable|integer',
            'ivl' => 'nullable|integer',
            'uc' => 'nullable|integer',
            'op' => 'nullable|integer',
            'vap' => 'nullable|integer',
            'iad' => 'nullable|integer',
            'pleb' => 'nullable|integer',
            'isk' => 'nullable|integer',
            'ido' => 'nullable|integer',
            'hap' => 'nullable|integer',
            'tinea' => 'nullable|integer',
            'scabies' => 'nullable|integer',
            'deku' => 'nullable|in:YA,TIDAK',
            'antibiotik' => 'nullable|string|max:200',
            'kategori' => 'nullable|string|max:100',
        ]);

        try {
            $hais = Hais::findOrFail($id);
            $hais->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Data HAIS berhasil diperbarui',
                'data' => $hais
            ]);
        } catch (\Throwable $th) {
            Log::error("Error update HAIS: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data HAIS: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display HAIS Report with aggregation.
     */
    public function report(Request $request)
    {
        $validated = $request->validate([
            'bulan' => 'required|numeric|between:1,12',
            'tahun' => 'required|numeric|min:2000',
        ]);

        try {
            $month = str_pad($validated['bulan'], 2, '0', STR_PAD_LEFT);
            $year = $validated['tahun'];
            $datePrefix = "{$year}-{$month}";

            $data = Hais::selectRaw('
                kategori as ruang,
                SUM(uc) as uc,
                SUM(isk) as isk,
                SUM(ett) as ett,
                SUM(vap) as vap,
                SUM(cvl) as cvl,
                SUM(iad) as iad,
                SUM(ivl) as ivl,
                SUM(pleb) as pleb,
                SUM(op) as op,
                SUM(ido) as ido,
                SUM(hap) as hap,
                SUM(tinea) as tinea,
                SUM(scabies) as scabies
            ')
            ->where('tanggal', 'like', "{$datePrefix}%")
            ->groupBy('kategori')
            ->get();

            // Calculate ratios
            $data = $data->map(function ($item) {
                // ISK Ratio (per-mille)
                $item->isk_ratio = $item->uc > 0 ? round(($item->isk / $item->uc) * 1000, 2) : 0;
                
                // VAP Ratio (per-mille)
                $item->vap_ratio = $item->ett > 0 ? round(($item->vap / $item->ett) * 1000, 2) : 0;
                
                // IAD Ratio (per-mille)
                $item->iad_ratio = $item->cvl > 0 ? round(($item->iad / $item->cvl) * 1000, 2) : 0;
                
                // Pleb Ratio (per-mille)
                $item->pleb_ratio = $item->ivl > 0 ? round(($item->pleb / $item->ivl) * 1000, 2) : 0;
                
                // IDO Ratio (percent)
                $item->ido_ratio = $item->op > 0 ? round(($item->ido / $item->op) * 100, 2) : 0;

                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Throwable $th) {
            Log::error("Error HAIS report: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat laporan HAIS: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified HAIS record.
     */
    public function destroy($id)
    {
        try {
            $hais = Hais::findOrFail($id);
            $hais->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data HAIS berhasil dihapus'
            ]);
        } catch (\Throwable $th) {
            Log::error("Error delete HAIS: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data HAIS: ' . $th->getMessage()
            ], 500);
        }
    }
}
