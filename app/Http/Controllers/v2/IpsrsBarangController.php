<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\IpsrsBarang;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IpsrsBarangController extends Controller
{
    public function index(Request $request)
    {
        $query = IpsrsBarang::with(['satuan', 'jenisBarang']);

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('nama_brng', 'like', '%' . $request->search . '%')
                  ->orWhere('kode_brng', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('jenis')) {
            $query->where('jenis', $request->jenis);
        }

        $data = $query->paginate($request->limit ?? 50);
        return ApiResponse::successWithData($data, 'Data barang berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_brng' => 'required|unique:ipsrsbarang,kode_brng|max:15',
            'nama_brng' => 'required|max:80',
            'kode_sat' => 'required|exists:kodesatuan,kode_sat',
            'jenis' => 'required|exists:ipsrsjenisbarang,kd_jenis',
            'stok' => 'required|numeric',
            'harga' => 'required|numeric',
            'status' => 'required|in:0,1'
        ]);

        try {
            $data = IpsrsBarang::create($request->all());
            return ApiResponse::success('Data barang berhasil ditambahkan', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal tambah data', 'store_error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $item = IpsrsBarang::find($id);
        if (!$item) return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);

        $request->validate([
            'nama_brng' => 'required|max:80',
            'kode_sat' => 'required|exists:kodesatuan,kode_sat',
            'jenis' => 'required|exists:ipsrsjenisbarang,kd_jenis',
            'stok' => 'required|numeric',
            'harga' => 'required|numeric',
            'status' => 'required|in:0,1'
        ]);

        try {
            $item->update($request->all());
            return ApiResponse::success('Data barang berhasil diupdate', $item);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal update data', 'update_error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $item = IpsrsBarang::find($id);
        if (!$item) return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);

        try {
            $item->delete();
            return ApiResponse::success('Data barang berhasil dihapus');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal hapus data', 'delete_error', $e->getMessage());
        }
    }

    public function getGeneratedCode()
    {
        // Find highest number in codes starting with 'B'
        // Assumes format B00001, B00002...
        $last = DB::table('ipsrsbarang')
            ->where('kode_brng', 'like', 'B%')
            ->selectRaw('MAX(CAST(SUBSTRING(kode_brng, 2) AS UNSIGNED)) as max_num')
            ->first();

        $nextNum = ($last->max_num ?? 0) + 1;
        
        // Format: B + 5 digits
        $nextCode = sprintf("B%05d", $nextNum);

        return ApiResponse::successWithData(['next_code' => $nextCode], 'Kode berhasil digenerate');
    }
}
