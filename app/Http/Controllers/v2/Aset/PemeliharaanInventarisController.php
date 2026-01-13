<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PemeliharaanInventaris;
use Illuminate\Http\Request;

class PemeliharaanInventarisController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);
        $sortBy = $request->input('sort_by', 'tanggal');
        $order = $request->input('order', 'desc');

        $query = PemeliharaanInventaris::with(['inventaris.barang', 'petugas']);

        if ($search) {
            $query->where('no_inventaris', 'like', "%{$search}%")
                  ->orWhere('uraian_kegiatan', 'like', "%{$search}%");
        }

        $data = $query->orderBy($sortBy, $order)->paginate($limit);

        return ApiResponse::successWithData($data, 'Data pemeliharaan inventaris berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'no_inventaris' => 'required|string|exists:inventaris,no_inventaris',
            'tanggal' => 'required|date',
            'uraian_kegiatan' => 'required|string|max:255',
            'nip' => 'required|string|exists:petugas,nip',
            'pelaksana' => 'required|in:Teknisi Rumah Sakit,Teknisi Rujukan,Pihak ke III',
            'biaya' => 'required|numeric',
            'jenis_pemeliharaan' => 'required|in:Running Maintenance,Shut Down Maintenance,Emergency Maintenance',
        ]);

        try {
            $data = PemeliharaanInventaris::create($request->all());
            return ApiResponse::successWithData($data, 'Data pemeliharaan berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menambahkan data pemeliharaan: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function update(Request $request)
    {
        // Composite key handling
        $request->validate([
            'no_inventaris' => 'required|string',
            'tanggal' => 'required|date',
            'uraian_kegiatan' => 'required|string|max:255',
            'nip' => 'required|string|exists:petugas,nip',
            'pelaksana' => 'required|in:Teknisi Rumah Sakit,Teknisi Rujukan,Pihak ke III',
            'biaya' => 'required|numeric',
            'jenis_pemeliharaan' => 'required|in:Running Maintenance,Shut Down Maintenance,Emergency Maintenance',
        ]);

        try {
            $record = PemeliharaanInventaris::where([
                'no_inventaris' => $request->no_inventaris,
                'tanggal' => $request->tanggal,
            ])->firstOrFail();

            $record->update($request->only(['uraian_kegiatan', 'nip', 'pelaksana', 'biaya', 'jenis_pemeliharaan']));

            return ApiResponse::successWithData($record, 'Data pemeliharaan berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui data pemeliharaan: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'no_inventaris' => 'required|string',
            'tanggal' => 'required|date',
        ]);

        try {
            $deleted = PemeliharaanInventaris::where([
                'no_inventaris' => $request->no_inventaris,
                'tanggal' => $request->tanggal,
            ])->delete();

            if ($deleted) {
                return ApiResponse::success('Data pemeliharaan berhasil dihapus');
            } else {
                return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);
            }
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menghapus data pemeliharaan: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
