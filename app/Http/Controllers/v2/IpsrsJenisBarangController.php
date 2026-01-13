<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\IpsrsJenisBarang;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class IpsrsJenisBarangController extends Controller
{
    public function index(Request $request)
    {
        $query = IpsrsJenisBarang::query();

        if ($request->has('search')) {
            $query->where('nm_jenis', 'like', '%' . $request->search . '%')
                  ->orWhere('kd_jenis', 'like', '%' . $request->search . '%');
        }

        $data = $query->paginate($request->limit ?? 50);
        return ApiResponse::successWithData($data, 'Data jenis barang berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kd_jenis' => 'required|unique:ipsrsjenisbarang,kd_jenis|max:5',
            'nm_jenis' => 'required'
        ]);

        try {
            $data = IpsrsJenisBarang::create($request->all());
            return ApiResponse::success('Data jenis barang berhasil ditambahkan', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal tambah data', 'store_error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $item = IpsrsJenisBarang::find($id);
        if (!$item) return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);

        $request->validate([
            'nm_jenis' => 'required'
        ]);

        try {
            $item->update($request->only('nm_jenis'));
            return ApiResponse::success('Data jenis barang berhasil diupdate', $item);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal update data', 'update_error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $item = IpsrsJenisBarang::find($id);
        if (!$item) return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);

        try {
            $item->delete();
            return ApiResponse::success('Data jenis barang berhasil dihapus');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal hapus data', 'delete_error', $e->getMessage());
        }
    }

    public function getGeneratedCode()
    {
        // Find highest number in codes starting with 'J'
        // Assumes format J01, J02, ... J100
        $last = \Illuminate\Support\Facades\DB::table('ipsrsjenisbarang')
            ->where('kd_jenis', 'like', 'J%')
            ->selectRaw('MAX(CAST(SUBSTRING(kd_jenis, 2) AS UNSIGNED)) as max_num')
            ->first();

        $nextNum = ($last->max_num ?? 0) + 1;
        
        // Format: J + 2 digits (e.g. J09, J10)
        $nextCode = sprintf("J%02d", $nextNum);

        return ApiResponse::successWithData(['next_code' => $nextCode], 'Kode berhasil digenerate');
    }
}
