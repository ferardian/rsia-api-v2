<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RsiaPpraMappingObat;
use App\Models\DataBarang;

class RsiaPpraMappingObatController extends Controller
{
    public function index(Request $request)
    {
        $query = RsiaPpraMappingObat::select('rsia_ppra_mapping_obat.*')
            ->join('databarang', 'rsia_ppra_mapping_obat.kode_brng', '=', 'databarang.kode_brng')
            ->with('barang')
            ->orderBy('databarang.nama_brng', 'asc');

        if ($request->has('keyword') && $request->keyword != '') {
            $keyword = $request->keyword;
            $query->where(function($q) use ($keyword) {
                $q->where('rute_pemberian', 'like', "%{$keyword}%")
                  ->orWhereHas('barang', function ($b) use ($keyword) {
                      $b->where('nama_brng', 'like', "%{$keyword}%")
                        ->orWhere('kode_brng', 'like', "%{$keyword}%");
                  });
            });
        }

        $data = $query->paginate($request->limit ?? 20);

        return response()->json([
            'success' => true,
            'message' => 'Data Mapping Obat PPRA',
            'data'    => $data
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_brng'       => 'required|exists:databarang,kode_brng',
            'rute_pemberian'  => 'nullable|string',
            'nilai_ddd_who'   => 'nullable|string',
            'status_notif'    => 'nullable|in:0,1',
        ]);

        if (RsiaPpraMappingObat::where('kode_brng', $request->kode_brng)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Obat ini sudah dimapping'
            ], 422);
        }

        $map = RsiaPpraMappingObat::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Mapping obat berhasil ditambahkan',
            'data'    => $map
        ]);
    }

    public function update(Request $request, $id)
    {
        $map = RsiaPpraMappingObat::find($id);

        if (!$map) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'rute_pemberian' => 'nullable|string',
            'nilai_ddd_who'  => 'nullable|string',
            'status_notif'   => 'nullable|in:0,1',
        ]);

        $map->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Mapping obat berhasil diperbarui',
            'data'    => $map
        ]);
    }

    public function destroy($id)
    {
        $map = RsiaPpraMappingObat::find($id);
        if ($map) {
            $map->delete();
            return response()->json(['success' => true, 'message' => 'Data berhasil dihapus']);
        }
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

    public function searchObat(Request $request)
    {
        $keyword = $request->keyword;
        $barang = DataBarang::where('status', '1') // Aktif
            ->where(function($q) use ($keyword) {
                $q->where('nama_brng', 'like', "%{$keyword}%")
                  ->orWhere('kode_brng', 'like', "%{$keyword}%");
            })
            ->whereDoesntHave('rsiaPpraMappingObat') // Belum di mapping (Optional, maybe user wants to see all)
            ->limit(20)
            ->get(['kode_brng', 'nama_brng', 'kode_sat']); // Select necessary fields

        return response()->json([
            'success' => true,
            'data' => $barang
        ]);
    }
}
