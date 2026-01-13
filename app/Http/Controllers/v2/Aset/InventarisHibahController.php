<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InventarisHibah;
use App\Models\InventarisDetailHibah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarisHibahController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);

        $query = InventarisHibah::with(['detail.barang']);

        if ($search) {
            $query->where('no_hibah', 'like', "%{$search}%");
        }

        $sortBy = $request->input('sort_by', 'tgl_hibah');
        $order = $request->input('order', 'desc');

        $data = $query->orderBy($sortBy, $order)->paginate($limit);

        return ApiResponse::successWithData($data, 'Data hibah berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'no_hibah' => 'required|string|max:20|unique:inventaris_hibah,no_hibah',
            'kode_pemberi' => 'nullable|string|max:5',
            'nip' => 'nullable|string|max:20',
            'tgl_hibah' => 'nullable|date',
            'totalhibah' => 'required|numeric',
            'kd_rek_aset' => 'nullable|string|max:15',
            'details' => 'required|array|min:1',
            'details.*.kode_barang' => 'required|string|exists:inventaris_barang,kode_barang',
            'details.*.jumlah' => 'required|numeric',
            'details.*.h_hibah' => 'required|numeric',
            'details.*.subtotalhibah' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            $hibah = InventarisHibah::create($request->except('details'));

            foreach ($request->details as $detail) {
                InventarisDetailHibah::create([
                    'no_hibah' => $hibah->no_hibah,
                    'kode_barang' => $detail['kode_barang'],
                    'jumlah' => $detail['jumlah'],
                    'h_hibah' => $detail['h_hibah'],
                    'subtotalhibah' => $detail['subtotalhibah'],
                ]);
            }

            DB::commit();
            return ApiResponse::successWithData($hibah->load('detail'), 'Data hibah berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Gagal menambahkan hibah: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function show($id)
    {
        try {
            $data = InventarisHibah::with(['detail.barang'])->findOrFail($id);
            return ApiResponse::successWithData($data, 'Data hibah berhasil diambil');
        } catch (\Exception $e) {
             return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);
        }
    }

    // Delete hibah will verify cascading deletion
    public function destroy($id)
    {
        try {
            $data = InventarisHibah::findOrFail($id);
            // Details should cascade delete due to DB setup, but if not we delete manually
            $data->detail()->delete(); 
            $data->delete();
            return ApiResponse::success('Data hibah berhasil dihapus');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menghapus hibah: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
