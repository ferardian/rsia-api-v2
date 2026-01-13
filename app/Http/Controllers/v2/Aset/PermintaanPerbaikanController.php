<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PermintaanPerbaikanInventaris;
use Illuminate\Http\Request;

class PermintaanPerbaikanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);
        $sortBy = $request->input('sort_by', 'tanggal');
        $order = $request->input('order', 'desc');

        $query = PermintaanPerbaikanInventaris::with(['inventaris.barang', 'pegawai', 'perbaikan_inventaris']);

        if ($search) {
            $query->where('no_permintaan', 'like', "%{$search}%")
                  ->orWhere('deskripsi_kerusakan', 'like', "%{$search}%")
                  ->orWhereHas('inventaris.barang', function($q) use ($search) {
                      $q->where('nama_barang', 'like', "%{$search}%");
                  });
        }

        $data = $query->orderBy($sortBy, $order)->paginate($limit);

        return ApiResponse::successWithData($data, 'Data permintaan perbaikan berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'no_permintaan' => 'required|string|max:15|unique:permintaan_perbaikan_inventaris,no_permintaan',
            'no_inventaris' => 'nullable|string|exists:inventaris,no_inventaris',
            'nik' => 'nullable|string|exists:pegawai,nik',
            'tanggal' => 'nullable|date',
            'deskripsi_kerusakan' => 'nullable|string|max:300',
        ]);

        try {
            $data = PermintaanPerbaikanInventaris::create($request->all());
            return ApiResponse::successWithData($data, 'Permintaan perbaikan berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan permintaan perbaikan: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function show($id)
    {
        try {
            $data = PermintaanPerbaikanInventaris::with(['inventaris.barang', 'pegawai', 'perbaikan_inventaris'])->findOrFail($id);
            return ApiResponse::successWithData($data, 'Data permintaan perbaikan berhasil diambil');
        } catch (\Exception $e) {
            return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'no_inventaris' => 'nullable|string|exists:inventaris,no_inventaris',
            'nik' => 'nullable|string|exists:pegawai,nik',
            'tanggal' => 'nullable|date',
            'deskripsi_kerusakan' => 'nullable|string|max:300',
        ]);

        try {
            $data = PermintaanPerbaikanInventaris::findOrFail($id);
            $data->update($request->except('no_permintaan'));
            return ApiResponse::successWithData($data, 'Permintaan perbaikan berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui permintaan perbaikan: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = PermintaanPerbaikanInventaris::findOrFail($id);
            $data->delete();
            return ApiResponse::success('Permintaan perbaikan berhasil dihapus');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                return ApiResponse::error('Gagal menghapus: Data sedang digunakan di tabel lain', 'constraint_error', null, 400);
            }
            return ApiResponse::error('Gagal menghapus permintaan perbaikan: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
