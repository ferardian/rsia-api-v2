<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\IpsrsSuplier;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IpsrsSuplierController extends Controller
{
    public function index(Request $request)
    {
        $query = IpsrsSuplier::query();

        if ($request->has('search')) {
            $query->where('nama_suplier', 'like', '%' . $request->search . '%')
                  ->orWhere('kode_suplier', 'like', '%' . $request->search . '%')
                  ->orWhere('kota', 'like', '%' . $request->search . '%');
        }

        $data = $query->paginate($request->limit ?? 50);
        return ApiResponse::successWithData($data, 'Data suplier berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_suplier' => 'required|unique:ipsrssuplier,kode_suplier|max:5',
            'nama_suplier' => 'required|max:50',
            'no_telp' => 'nullable|max:13'
        ]);

        try {
            $data = IpsrsSuplier::create($request->all());
            return ApiResponse::success('Data suplier berhasil ditambahkan', $data);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal tambah data', 'store_error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $item = IpsrsSuplier::find($id);
        if (!$item) return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);

        $request->validate([
            'nama_suplier' => 'required|max:50'
        ]);

        try {
            $item->update($request->all());
            return ApiResponse::success('Data suplier berhasil diupdate', $item);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal update data', 'update_error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $item = IpsrsSuplier::find($id);
        if (!$item) return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);

        try {
            $item->delete();
            return ApiResponse::success('Data suplier berhasil dihapus');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal hapus data', 'delete_error', $e->getMessage());
        }
    }

    public function getGeneratedCode()
    {
        // Find highest number in codes starting with 'S'
        // Assumes format S0001
        $last = DB::table('ipsrssuplier')
            ->where('kode_suplier', 'like', 'S%')
            ->selectRaw('MAX(CAST(SUBSTRING(kode_suplier, 2) AS UNSIGNED)) as max_num')
            ->first();

        $nextNum = ($last->max_num ?? 0) + 1;
        
        // Format: S + 4 digits (e.g. S0001) because char(5) -> S + 4 chars
        $nextCode = sprintf("S%04d", $nextNum);

        return ApiResponse::successWithData(['next_code' => $nextCode], 'Kode berhasil digenerate');
    }
}
