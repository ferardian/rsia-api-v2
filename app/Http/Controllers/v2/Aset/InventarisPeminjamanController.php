<?php

namespace App\Http\Controllers\v2\Aset;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\InventarisPeminjaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarisPeminjamanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 15);

        $query = InventarisPeminjaman::with(['inventaris.barang', 'pegawai']);

        if ($search) {
            $query->where('peminjam', 'like', "%{$search}%")
                  ->orWhereHas('inventaris.barang', function($q) use ($search) {
                      $q->where('nama_barang', 'like', "%{$search}%");
                  });
        }

        $data = $query->orderBy('tgl_pinjam', 'desc')->paginate($limit);

        return ApiResponse::successWithData($data, 'Data peminjaman berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'peminjam' => 'required|string|max:50',
            'tlp' => 'required|string|max:13',
            'no_inventaris' => 'required|string|exists:inventaris,no_inventaris',
            'tgl_pinjam' => 'required|date',
            'nip' => 'required|string|max:20', // Ensure relation assumes exist check if strict
            'status_pinjam' => 'required|in:Masih Dipinjam,Sudah Kembali',
        ]);

        try {
            // Using DB logic since this table has composite Primary Key
            $data = InventarisPeminjaman::create($request->all());
            
            // Should also update status_barang in inventaris table automatically?
            // If status_pinjam == 'Masih Dipinjam', update inventaris status to 'Dipinjam'
             if ($request->status_pinjam == 'Masih Dipinjam') {
                \App\Models\Inventaris::where('no_inventaris', $request->no_inventaris)
                    ->update(['status_barang' => 'Dipinjam']);
            }

            return ApiResponse::successWithData($data, 'Peminjaman berhasil dicatat', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal mencatat peminjaman: ' . $e->getMessage(), 'store_error', null, 500);
        }
    }

    public function update(Request $request)
    {
        // Because of composite keys, update usually refers to changing status_pinjam or tgl_kembali
        // We'll require keys to find the record
        $request->validate([
            'peminjam' => 'required',
            'no_inventaris' => 'required',
            'tgl_pinjam' => 'required|date',
            'nip' => 'required',
            'tgl_kembali' => 'nullable|date',
            'status_pinjam' => 'required|in:Masih Dipinjam,Sudah Kembali',
        ]);

        try {
            $record = InventarisPeminjaman::where([
                'peminjam' => $request->peminjam,
                'no_inventaris' => $request->no_inventaris,
                'tgl_pinjam' => $request->tgl_pinjam,
                'nip' => $request->nip,
            ])->firstOrFail();

            $record->update($request->only('tgl_kembali', 'status_pinjam', 'tlp'));

            if ($request->status_pinjam == 'Sudah Kembali') {
                 \App\Models\Inventaris::where('no_inventaris', $request->no_inventaris)
                    ->update(['status_barang' => 'Ada']);
            } elseif ($request->status_pinjam == 'Masih Dipinjam') {
                 \App\Models\Inventaris::where('no_inventaris', $request->no_inventaris)
                    ->update(['status_barang' => 'Dipinjam']);
            }

            return ApiResponse::successWithData($record, 'Data peminjaman diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui peminjaman: ' . $e->getMessage(), 'update_error', null, 500);
        }
    }

    public function destroy(Request $request)
    {
         $request->validate([
            'peminjam' => 'required',
            'no_inventaris' => 'required',
            'tgl_pinjam' => 'required|date',
            'nip' => 'required',
        ]);

        try {
            $deleted = InventarisPeminjaman::where([
                'peminjam' => $request->peminjam,
                'no_inventaris' => $request->no_inventaris,
                'tgl_pinjam' => $request->tgl_pinjam,
                'nip' => $request->nip,
            ])->delete();

            if ($deleted) {
                // Return inventaris status to Ada? Maybe check if no other active loans exist
                // Ideally reverting status_barang depends on logic. Assuming 'Ada' if deleted loan.
                 \App\Models\Inventaris::where('no_inventaris', $request->no_inventaris)
                    ->update(['status_barang' => 'Ada']);

                return ApiResponse::success('Data peminjaman dihapus');
            } else {
                 return ApiResponse::error('Data tidak ditemukan', 'not_found', null, 404);
            }
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menghapus peminjaman: ' . $e->getMessage(), 'destroy_error', null, 500);
        }
    }
}
