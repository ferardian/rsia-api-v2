<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Inventaris;
use Illuminate\Http\Request;

use App\Traits\LogsToTracker;

class InventarisController extends Controller
{
    use LogsToTracker;
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);

        $query = Inventaris::with(['barang', 'ruang']);

        if ($search) {
            $query->whereHas('barang', function ($q) use ($search) {
                $q->where('nama_barang', 'like', "%{$search}%");
            })->orWhere('no_inventaris', 'like', "%{$search}%");
        }

        $sortBy = $request->input('sort_by', 'tgl_pengadaan');
        $order = $request->input('order', 'desc');

        $data = $query->orderBy($sortBy, $order)->paginate($limit);

        return ApiResponse::successWithData($data, 'Data inventaris berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'no_inventaris' => 'required|string|max:30|unique:inventaris,no_inventaris',
            'kode_barang' => 'required|string|exists:inventaris_barang,kode_barang',
            'asal_barang' => 'required|in:Beli,Bantuan,Hibah,-',
            'tgl_pengadaan' => 'nullable|date',
            'harga' => 'nullable|numeric',
            'status_barang' => 'required|in:Ada,Rusak,Hilang,Perbaikan,Dipinjam,-',
            'id_ruang' => 'nullable|string|exists:inventaris_ruang,id_ruang',
            'no_rak' => 'nullable|string|max:3',
            'no_box' => 'nullable|string|max:3',
        ]);

        try {
            $data = Inventaris::create($request->all());

            $sql = "INSERT INTO inventaris VALUES ('{$request->no_inventaris}', '{$request->kode_barang}', '{$request->asal_barang}', '{$request->tgl_pengadaan}', '{$request->harga}', '{$request->status_barang}', '{$request->id_ruang}', '{$request->no_rak}', '{$request->no_box}')";
            $this->logTracker($sql, $request);

            return ApiResponse::successWithData($data, 'Data inventaris berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan data inventaris: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function show($id)
    {
        try {
            $data = Inventaris::with(['barang', 'ruang'])->findOrFail($id);
            return ApiResponse::successWithData($data, 'Data inventaris berhasil diambil');
        } catch (\Exception $e) {
            return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'kode_barang' => 'required|string|exists:inventaris_barang,kode_barang',
            'asal_barang' => 'required|in:Beli,Bantuan,Hibah,-',
            'tgl_pengadaan' => 'nullable|date',
            'harga' => 'nullable|numeric',
            'status_barang' => 'required|in:Ada,Rusak,Hilang,Perbaikan,Dipinjam,-',
            'id_ruang' => 'nullable|string|exists:inventaris_ruang,id_ruang',
            'no_rak' => 'nullable|string|max:3',
            'no_box' => 'nullable|string|max:3',
        ]);

        try {
            $data = Inventaris::findOrFail($id);
            $data->update($request->except('no_inventaris'));

            $sql = "UPDATE inventaris SET kode_barang='{$request->kode_barang}', asal_barang='{$request->asal_barang}', tgl_pengadaan='{$request->tgl_pengadaan}', harga='{$request->harga}', status_barang='{$request->status_barang}', id_ruang='{$request->id_ruang}', no_rak='{$request->no_rak}', no_box='{$request->no_box}' WHERE no_inventaris='{$id}'";
            $this->logTracker($sql, $request);

            return ApiResponse::successWithData($data, 'Data inventaris berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui data inventaris: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = Inventaris::findOrFail($id);
            $data->delete();

            $sql = "DELETE FROM inventaris WHERE no_inventaris='{$id}'";
            $this->logTracker($sql, request());

            return ApiResponse::success('Data inventaris berhasil dihapus');
        } catch (\Exception $e) {
             if (str_contains($e->getMessage(), 'foreign key constraint')) {
                 return ApiResponse::error('Gagal menghapus: Data sedang digunakan di tabel lain', 'constraint_error', null, 400);
            }
            return ApiResponse::error('Gagal menghapus data inventaris: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
