<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InventarisKategori;
use Illuminate\Http\Request;

class InventarisKategoriController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);

        $query = InventarisKategori::query();

        if ($search) {
            $query->where('nama_kategori', 'like', "%{$search}%")
                  ->orWhere('id_kategori', 'like', "%{$search}%");
        }

        $data = $query->orderBy('nama_kategori')->paginate($limit);

        return ApiResponse::successWithData($data, 'Data kategori inventaris berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_kategori' => 'required|string|max:10|unique:inventaris_kategori,id_kategori',
            'nama_kategori' => 'required|string|max:40',
        ]);

        try {
            $data = InventarisKategori::create($request->all());
            return ApiResponse::successWithData($data, 'Kategori inventaris berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan kategori inventaris: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_kategori' => 'required|string|max:40',
        ]);

        try {
            $data = InventarisKategori::findOrFail($id);
            $data->update($request->only('nama_kategori'));
            return ApiResponse::successWithData($data, 'Kategori inventaris berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui kategori inventaris: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = InventarisKategori::findOrFail($id);
            $data->delete();
            return ApiResponse::success('Kategori inventaris berhasil dihapus');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                 return ApiResponse::error('Gagal menghapus: Data sedang digunakan di tabel lain', 'constraint_error', null, 400);
            }
            return ApiResponse::error('Gagal menghapus kategori inventaris: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
