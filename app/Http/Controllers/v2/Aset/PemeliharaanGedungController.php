<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PemeliharaanGedung;
use Illuminate\Http\Request;

class PemeliharaanGedungController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);
        $sortBy = $request->input('sort_by', 'tanggal');
        $order = $request->input('order', 'desc');

        $query = PemeliharaanGedung::with(['petugas']);

        if ($search) {
            $query->where('no_pemeliharaan', 'like', "%{$search}%")
                  ->orWhere('uraian_kegiatan', 'like', "%{$search}%")
                  ->orWhere('tindak_lanjut', 'like', "%{$search}%");
        }

        $data = $query->orderBy($sortBy, $order)->paginate($limit);

        return ApiResponse::successWithData($data, 'Data pemeliharaan gedung berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'no_pemeliharaan' => 'required|string|max:20|unique:pemeliharaan_gedung,no_pemeliharaan',
            'tanggal' => 'required|date',
            'uraian_kegiatan' => 'required|string|max:255',
            'nip' => 'required|string|exists:petugas,nip',
            'pelaksana' => 'required|in:Teknisi Rumah Sakit,Pihak ke III',
            'biaya' => 'required|numeric',
            'jenis_pemeliharaan' => 'required|in:Running Maintenance,Shut Down Maintenance,Emergency Maintenance',
            'tindak_lanjut' => 'required|string|max:100',
        ]);

        try {
            $data = PemeliharaanGedung::create($request->all());
            return ApiResponse::successWithData($data, 'Data pemeliharaan gedung berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan data pemeliharaan gedung: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function show($id)
    {
        try {
            $data = PemeliharaanGedung::with(['petugas'])->findOrFail($id);
            return ApiResponse::successWithData($data, 'Data pemeliharaan gedung berhasil diambil');
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
            'pelaksana' => 'required|in:Teknisi Rumah Sakit,Pihak ke III',
            'biaya' => 'required|numeric',
            'jenis_pemeliharaan' => 'required|in:Running Maintenance,Shut Down Maintenance,Emergency Maintenance',
            'tindak_lanjut' => 'required|string|max:100',
        ]);

        try {
            $data = PemeliharaanGedung::findOrFail($id);
            $data->update($request->except('no_pemeliharaan'));
            return ApiResponse::successWithData($data, 'Data pemeliharaan gedung berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui data pemeliharaan gedung: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = PemeliharaanGedung::findOrFail($id);
            $data->delete();
            return ApiResponse::success('Data pemeliharaan gedung berhasil dihapus');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menghapus data pemeliharaan gedung: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
