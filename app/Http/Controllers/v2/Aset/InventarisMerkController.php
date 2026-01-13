<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InventarisMerk;
use Illuminate\Http\Request;

class InventarisMerkController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);

        $query = InventarisMerk::query();

        if ($search) {
            $query->where('nama_merk', 'like', "%{$search}%")
                  ->orWhere('id_merk', 'like', "%{$search}%");
        }

        $data = $query->orderBy('nama_merk')->paginate($limit);

        return ApiResponse::successWithData($data, 'Data merk inventaris berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_merk' => 'required|string|max:10|unique:inventaris_merk,id_merk',
            'nama_merk' => 'required|string|max:40',
        ]);

        try {
            $data = InventarisMerk::create($request->all());
            return ApiResponse::successWithData($data, 'Merk inventaris berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan merk inventaris: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_merk' => 'required|string|max:40',
        ]);

        try {
            $data = InventarisMerk::findOrFail($id);
            $data->update($request->only('nama_merk'));
            return ApiResponse::successWithData($data, 'Merk inventaris berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui merk inventaris: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = InventarisMerk::findOrFail($id);
            $data->delete();
            return ApiResponse::success('Merk inventaris berhasil dihapus');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                 return ApiResponse::error('Gagal menghapus: Data sedang digunakan di tabel lain', 'constraint_error', null, 400);
            }
            return ApiResponse::error('Gagal menghapus merk inventaris: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
