<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PerbaikanInventaris;
use Illuminate\Http\Request;

class PerbaikanInventarisController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);
        $sortBy = $request->input('sort_by', 'tanggal');
        $order = $request->input('order', 'desc');

        $query = PerbaikanInventaris::with(['permintaan_perbaikan.inventaris.barang', 'petugas']);

        if ($search) {
            $query->where('no_permintaan', 'like', "%{$search}%")
                  ->orWhere('uraian_kegiatan', 'like', "%{$search}%");
        }

        $data = $query->orderBy($sortBy, $order)->paginate($limit);

        return ApiResponse::successWithData($data, 'Data perbaikan inventaris berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'no_permintaan' => 'required|string|exists:permintaan_perbaikan_inventaris,no_permintaan|unique:perbaikan_inventaris,no_permintaan',
            'tanggal' => 'required|date',
            'uraian_kegiatan' => 'required|string|max:255',
            'nip' => 'required|string|exists:petugas,nip',
            'pelaksana' => 'required|in:Teknisi Rumah Sakit,Teknisi Rujukan,Pihak ke III',
            'biaya' => 'required|numeric',
            'keterangan' => 'required|string|max:255',
            'status' => 'required|in:Bisa Diperbaiki,Tidak Bisa Diperbaiki',
        ]);

        try {
            $data = PerbaikanInventaris::create($request->all());
            return ApiResponse::successWithData($data, 'Data perbaikan berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan data perbaikan: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function show($id)
    {
        try {
            $data = PerbaikanInventaris::with(['permintaan_perbaikan.inventaris.barang', 'petugas'])->findOrFail($id);
            return ApiResponse::successWithData($data, 'Data perbaikan berhasil diambil');
        } catch (\Exception $e) {
            return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'uraian_kegiatan' => 'required|string|max:255',
            'nip' => 'required|string|exists:petugas,nip',
            'pelaksana' => 'required|in:Teknisi Rumah Sakit,Teknisi Rujukan,Pihak ke III',
            'biaya' => 'required|numeric',
            'keterangan' => 'required|string|max:255',
            'status' => 'required|in:Bisa Diperbaiki,Tidak Bisa Diperbaiki',
        ]);

        try {
            $data = PerbaikanInventaris::findOrFail($id);
            $data->update($request->except('no_permintaan'));
            return ApiResponse::successWithData($data, 'Data perbaikan berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui data perbaikan: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = PerbaikanInventaris::findOrFail($id);
            $data->delete();
            return ApiResponse::success('Data perbaikan berhasil dihapus');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menghapus data perbaikan: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
