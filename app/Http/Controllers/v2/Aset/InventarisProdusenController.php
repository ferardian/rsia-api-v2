<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InventarisProdusen;
use Illuminate\Http\Request;

class InventarisProdusenController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);

        $query = InventarisProdusen::query();

        if ($search) {
            $query->where('nama_produsen', 'like', "%{$search}%")
                  ->orWhere('kode_produsen', 'like', "%{$search}%");
        }

        $data = $query->orderBy('nama_produsen')->paginate($limit);

        return ApiResponse::successWithData($data, 'Data produsen inventaris berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_produsen' => 'required|string|max:10|unique:inventaris_produsen,kode_produsen',
            'nama_produsen' => 'required|string|max:40',
            'alamat_produsen' => 'nullable|string|max:70',
            'no_telp' => 'nullable|string|max:13',
            'email' => 'nullable|string|max:25',
            'website_produsen' => 'nullable|string|max:30',
        ]);

        try {
            $data = InventarisProdusen::create($request->all());
            return ApiResponse::successWithData($data, 'Produsen inventaris berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan produsen inventaris: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_produsen' => 'required|string|max:40',
            'alamat_produsen' => 'nullable|string|max:70',
            'no_telp' => 'nullable|string|max:13',
            'email' => 'nullable|string|max:25',
            'website_produsen' => 'nullable|string|max:30',
        ]);

        try {
            $data = InventarisProdusen::findOrFail($id);
            $data->update($request->except('kode_produsen'));
            return ApiResponse::successWithData($data, 'Produsen inventaris berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui produsen inventaris: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = InventarisProdusen::findOrFail($id);
            $data->delete();
            return ApiResponse::success('Produsen inventaris berhasil dihapus');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                 return ApiResponse::error('Gagal menghapus: Data sedang digunakan di tabel lain', 'constraint_error', null, 400);
            }
            return ApiResponse::error('Gagal menghapus produsen inventaris: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
