<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InventarisBarang;
use Illuminate\Http\Request;

class InventarisBarangController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);

        $query = InventarisBarang::with(['produsen', 'merk', 'kategori', 'jenis']);

        $sortBy = $request->input('sort_by', 'nama_barang');
        $order = $request->input('order', 'asc');

        if ($search) {
            $query->where('nama_barang', 'like', "%{$search}%")
                  ->orWhere('kode_barang', 'like', "%{$search}%");
        }

        $data = $query->orderBy($sortBy, $order)->paginate($limit);

        return ApiResponse::successWithData($data, 'Data barang inventaris berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_barang' => 'required|string|max:20|unique:inventaris_barang,kode_barang',
            'nama_barang' => 'required|string|max:60',
            'jml_barang' => 'nullable|integer',
            'kode_produsen' => 'nullable|string|exists:inventaris_produsen,kode_produsen',
            'id_merk' => 'nullable|string|exists:inventaris_merk,id_merk',
            'thn_produksi' => 'nullable|integer',
            'isbn' => 'nullable|string|max:20',
            'id_kategori' => 'nullable|string|exists:inventaris_kategori,id_kategori',
            'id_jenis' => 'nullable|string|exists:inventaris_jenis,id_jenis',
        ]);

        try {
            $data = InventarisBarang::create($request->all());
            return ApiResponse::successWithData($data, 'Barang inventaris berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan barang inventaris: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function show($id)
    {
        try {
            $data = InventarisBarang::with(['produsen', 'merk', 'kategori', 'jenis'])->findOrFail($id);
            return ApiResponse::successWithData($data, 'Data barang inventaris berhasil diambil');
        } catch (\Exception $e) {
            return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_barang' => 'required|string|max:60',
            'jml_barang' => 'nullable|integer',
            'kode_produsen' => 'nullable|string|exists:inventaris_produsen,kode_produsen',
            'id_merk' => 'nullable|string|exists:inventaris_merk,id_merk',
            'thn_produksi' => 'nullable|integer',
            'isbn' => 'nullable|string|max:20',
            'id_kategori' => 'nullable|string|exists:inventaris_kategori,id_kategori',
            'id_jenis' => 'nullable|string|exists:inventaris_jenis,id_jenis',
        ]);

        try {
            $data = InventarisBarang::findOrFail($id);
            $data->update($request->except('kode_barang'));
            return ApiResponse::successWithData($data, 'Barang inventaris berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui barang inventaris: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = InventarisBarang::findOrFail($id);
            $data->delete();
            return ApiResponse::success('Barang inventaris berhasil dihapus');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                 return ApiResponse::error('Gagal menghapus: Data sedang digunakan di tabel lain', 'constraint_error', null, 400);
            }
            return ApiResponse::error('Gagal menghapus barang inventaris: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
