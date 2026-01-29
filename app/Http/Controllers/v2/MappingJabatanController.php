<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaMappingJabatan;
use Illuminate\Http\Request;

class MappingJabatanController extends Controller
{
    public function index(Request $request)
    {
        $query = RsiaMappingJabatan::query()
            ->leftJoin('jnj_jabatan as up_jabatan', 'rsia_mapping_jabatan.kd_jabatan_up', '=', 'up_jabatan.kode')
            ->leftJoin('departemen as up_departemen', 'rsia_mapping_jabatan.dep_id_up', '=', 'up_departemen.dep_id')
            ->leftJoin('jnj_jabatan as down_jabatan', 'rsia_mapping_jabatan.kd_jabatan_down', '=', 'down_jabatan.kode')
            ->leftJoin('departemen as down_departemen', 'rsia_mapping_jabatan.dep_id_down', '=', 'down_departemen.dep_id')
            ->select(
                'rsia_mapping_jabatan.*',
                'up_jabatan.nama as nama_jabatan_up',
                'up_departemen.nama as nama_departemen_up',
                'down_jabatan.nama as nama_jabatan_down',
                'down_departemen.nama as nama_departemen_down'
            );

        if ($request->has('keyword') && !empty($request->keyword)) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('up_jabatan.nama', 'like', "%{$keyword}%")
                  ->orWhere('down_jabatan.nama', 'like', "%{$keyword}%")
                  ->orWhere('up_departemen.nama', 'like', "%{$keyword}%")
                  ->orWhere('down_departemen.nama', 'like', "%{$keyword}%");
            });
        }

        $data = $query->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'message' => 'Data mapping jabatan berhasil diambil',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dep_id_up' => 'required|string',
            'kd_jabatan_up' => 'required|string',
            'dep_id_down' => 'required|string',
            'kd_jabatan_down' => 'required|string',
        ]);

        // Check for duplicates
        $exists = RsiaMappingJabatan::where($validated)->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Mapping jabatan tersebut sudah ada'
            ], 422);
        }

        $mapping = RsiaMappingJabatan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Mapping jabatan berhasil ditambahkan',
            'data' => $mapping
        ]);
    }

    public function update(Request $request, $id)
    {
        $mapping = RsiaMappingJabatan::find($id);
        if (!$mapping) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'dep_id_up' => 'required|string',
            'kd_jabatan_up' => 'required|string',
            'dep_id_down' => 'required|string',
            'kd_jabatan_down' => 'required|string',
        ]);

        // Check for duplicates excluding self
        $exists = RsiaMappingJabatan::where($validated)
            ->where('id', '!=', $id)
            ->exists();
            
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Mapping jabatan tersebut sudah ada'
            ], 422);
        }

        $mapping->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Mapping jabatan berhasil diupdate',
            'data' => $mapping
        ]);
    }

    public function destroy($id)
    {
        $mapping = RsiaMappingJabatan::find($id);
        if (!$mapping) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $mapping->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mapping jabatan berhasil dihapus'
        ]);
    }

    public function getJabatanList(Request $request)
    {
        $query = \App\Models\JnjJabatan::query();

        if ($request->has('keyword') && !empty($request->keyword)) {
            $query->where('nama', 'like', "%{$request->keyword}%")
                  ->orWhere('kode', 'like', "%{$request->keyword}%");
        }

        $data = $query->limit(50)->get(['kode', 'nama']);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
