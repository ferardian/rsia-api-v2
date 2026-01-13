<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InventarisSuplier;
use Illuminate\Http\Request;

class InventarisSuplierController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);

        $query = InventarisSuplier::query();

        if ($search) {
            $query->where('nama_suplier', 'like', "%{$search}%")
                  ->orWhere('kode_suplier', 'like', "%{$search}%");
        }

        $data = $query->orderBy('nama_suplier')->paginate($limit);

        return ApiResponse::successWithData($data, 'Data suplier inventaris berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_suplier' => 'required|string|max:5|unique:inventaris_suplier,kode_suplier',
            'nama_suplier' => 'nullable|string|max:50', // Based on SQL "DEFAULT NULL" but usually name is required, following model for safety, but making "nullable" in validation if user wants it optional? Let's check schema. Schema says DEFAULT NULL.
            'alamat' => 'nullable|string|max:50',
            'kota' => 'nullable|string|max:20',
            'no_telp' => 'nullable|string|max:13',
            'nama_bank' => 'nullable|string|max:30',
            'rekening' => 'nullable|string|max:20',
        ]);

        try {
            $data = InventarisSuplier::create($request->all());
            return ApiResponse::successWithData($data, 'Suplier inventaris berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan suplier inventaris: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_suplier' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:50',
            'kota' => 'nullable|string|max:20',
            'no_telp' => 'nullable|string|max:13',
            'nama_bank' => 'nullable|string|max:30',
            'rekening' => 'nullable|string|max:20',
        ]);

        try {
            $data = InventarisSuplier::findOrFail($id);
            $data->update($request->except('kode_suplier'));
            return ApiResponse::successWithData($data, 'Suplier inventaris berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui suplier inventaris: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = InventarisSuplier::findOrFail($id);
            $data->delete();
            return ApiResponse::success('Suplier inventaris berhasil dihapus');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                 return ApiResponse::error('Gagal menghapus: Data sedang digunakan di tabel lain', 'constraint_error', null, 400);
            }
            return ApiResponse::error('Gagal menghapus suplier inventaris: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
