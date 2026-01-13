<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InventarisJenis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarisJenisController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);

        $query = InventarisJenis::query();

        if ($search) {
            $query->where('nama_jenis', 'like', "%{$search}%")
                  ->orWhere('id_jenis', 'like', "%{$search}%");
        }

        $data = $query->orderBy('nama_jenis')->paginate($limit);

        return ApiResponse::successWithData($data, 'Data jenis inventaris berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_jenis' => 'required|string|max:10|unique:inventaris_jenis,id_jenis',
            'nama_jenis' => 'required|string|max:40',
        ]);

        try {
            $data = InventarisJenis::create($request->all());
            return ApiResponse::successWithData($data, 'Jenis inventaris berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan jenis inventaris: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_jenis' => 'required|string|max:40',
        ]);

        try {
            $data = InventarisJenis::findOrFail($id);
            $data->update($request->only('nama_jenis'));
            return ApiResponse::successWithData($data, 'Jenis inventaris berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui jenis inventaris: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = InventarisJenis::findOrFail($id);
            $data->delete();
            return ApiResponse::success('Jenis inventaris berhasil dihapus');
        } catch (\Exception $e) {
            // Check for constraint violation if referenced
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                 return ApiResponse::error('Gagal menghapus: Data sedang digunakan di tabel lain', 'constraint_error', null, 400);
            }
            return ApiResponse::error('Gagal menghapus jenis inventaris: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
