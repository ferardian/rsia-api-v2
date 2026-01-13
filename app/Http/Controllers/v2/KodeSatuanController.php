<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\KodeSatuan;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KodeSatuanController extends Controller
{
    public function index(Request $request)
    {
        $query = KodeSatuan::query();

        if ($request->has('search')) {
            $query->where('satuan', 'like', '%' . $request->search . '%')
                  ->orWhere('kode_sat', 'like', '%' . $request->search . '%');
        }

        $data = $query->paginate($request->limit ?? 50);
        return ApiResponse::successWithData($data, 'Data satuan berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_sat' => 'required|unique:kodesatuan,kode_sat|max:4',
            'satuan' => 'required'
        ]);

        try {
            $data = KodeSatuan::create($request->all());
            return ApiResponse::success('Data satuan berhasil ditambahkan', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal tambah data', 'store_error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $item = KodeSatuan::find($id);
        if (!$item) return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);

        $request->validate([
            'satuan' => 'required'
        ]);

        try {
            $item->update($request->only('satuan'));
            return ApiResponse::success('Data satuan berhasil diupdate', $item);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal update data', 'update_error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $item = KodeSatuan::find($id);
        if (!$item) return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);

        try {
            $item->delete();
            return ApiResponse::success('Data satuan berhasil dihapus');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal hapus data', 'delete_error', $e->getMessage());
        }
    }
}
