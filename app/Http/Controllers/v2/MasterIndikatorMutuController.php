<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaMasterInmut;
use App\Models\RsiaMasterInmutUtama;
use Illuminate\Http\Request;

class MasterIndikatorMutuController extends Controller
{
    // === Master Utama ===

    public function indexUtama(Request $request)
    {
        $query = RsiaMasterInmutUtama::query();

        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->where('nama_inmut', 'like', "%{$keyword}%")
                  ->orWhere('kategori', 'like', "%{$keyword}%");
        }

        if ($request->has('kategori') && $request->kategori) {
            $query->where('kategori', $request->kategori);
        }

        $data = $query->paginate($request->limit ?? 10);

        return response()->json([
            'success' => true,
            'message' => 'Data Master Inmut Utama berhasil diambil',
            'data' => $data
        ]);
    }

    public function storeUtama(Request $request)
    {
        $data = RsiaMasterInmutUtama::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Data Master Inmut Utama berhasil disimpan',
            'data' => $data
        ]);
    }

    public function updateUtama(Request $request, $id)
    {
        $master = RsiaMasterInmutUtama::find($id);

        if (!$master) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $master->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Data Master Inmut Utama berhasil diupdate',
            'data' => $master
        ]);
    }

    public function destroyUtama($id)
    {
        $master = RsiaMasterInmutUtama::find($id);

        if (!$master) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $master->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data Master Inmut Utama berhasil dihapus'
        ]);
    }

    // === Master Ruang ===

    public function indexRuang(Request $request)
    {
        $query = RsiaMasterInmut::query();

        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->where('nama_inmut', 'like', "%{$keyword}%")
                  ->orWhere('nama_ruang', 'like', "%{$keyword}%");
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('dep_id')) {
            $query->where('dep_id', $request->dep_id);
        }

        // Include latest input date
        $query->addSelect(['latest_input_date' => function ($q) {
            $q->selectRaw('max(tanggal_inmut)')
              ->from('rsia_rekap_inmut')
              ->whereColumn('rsia_rekap_inmut.id_inmut', 'rsia_master_inmut.id_inmut')
              ->whereColumn('rsia_rekap_inmut.dep_id', 'rsia_master_inmut.dep_id');
        }]);

        $query->with('masterUtama');

        $data = $query->paginate($request->limit ?? 10);

        return response()->json([
            'success' => true,
            'message' => 'Data Master Inmut Ruang berhasil diambil',
            'data' => $data
        ]);
    }

    public function storeRuang(Request $request)
    {
        $data = RsiaMasterInmut::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Data Master Inmut Ruang berhasil disimpan',
            'data' => $data
        ]);
    }

    public function updateRuang(Request $request, $id)
    {
        $master = RsiaMasterInmut::find($id);

        if (!$master) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $master->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Data Master Inmut Ruang berhasil diupdate',
            'data' => $master
        ]);
    }

    public function destroyRuang($id)
    {
        $master = RsiaMasterInmut::find($id);

        if (!$master) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $master->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data Master Inmut Ruang berhasil dihapus'
        ]);
    }
}
