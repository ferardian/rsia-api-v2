<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InventarisRuang;
use Illuminate\Http\Request;

class InventarisRuangController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);

        $query = InventarisRuang::query();

        if ($search) {
            $query->where('nama_ruang', 'like', "%{$search}%")
                  ->orWhere('id_ruang', 'like', "%{$search}%");
        }

        $data = $query->orderBy('nama_ruang')->paginate($limit);

        return ApiResponse::successWithData($data, 'Data ruang inventaris berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_ruang' => 'required|string|max:5|unique:inventaris_ruang,id_ruang',
            'nama_ruang' => 'required|string|max:40',
        ]);

        try {
            $data = InventarisRuang::create($request->all());
            return ApiResponse::successWithData($data, 'Ruang inventaris berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan ruang inventaris: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_ruang' => 'required|string|max:40',
        ]);

        try {
            $data = InventarisRuang::findOrFail($id);
            $data->update($request->only('nama_ruang'));
            return ApiResponse::successWithData($data, 'Ruang inventaris berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui ruang inventaris: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = InventarisRuang::findOrFail($id);
            $data->delete();
            return ApiResponse::success('Ruang inventaris berhasil dihapus');
        } catch (\Exception $e) {
             if (str_contains($e->getMessage(), 'foreign key constraint')) {
                 return ApiResponse::error('Gagal menghapus: Data sedang digunakan di tabel lain', 'constraint_error', null, 400);
            }
            return ApiResponse::error('Gagal menghapus ruang inventaris: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
