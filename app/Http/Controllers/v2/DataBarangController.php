<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\DataBarang;
use Illuminate\Http\Request;

class DataBarangController extends Controller
{
    public function index(Request $request)
    {
        $query = DataBarang::query()
            ->with(['kategori', 'satuan', 'satuanBesar', 'jenis', 'industri', 'golongan'])
            ->orderBy('nama_brng');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '<>', '0');
        }

        if ($request->has('q')) {
            $q = $request->q;
            $query->where(function ($query) use ($q) {
                $query->where('kode_brng', 'like', "%{$q}%")
                    ->orWhere('nama_brng', 'like', "%{$q}%")
                    ->orWhere('kapasitas', 'like', "%{$q}%")
                    ->orWhere('letak_barang', 'like', "%{$q}%")
                    ->orWhere('expire', 'like', "%{$q}%")
                    ->orWhereHas('kategori', function ($query) use ($q) {
                        $query->where('nama', 'like', "%{$q}%");
                    })
                    ->orWhereHas('jenis', function ($query) use ($q) {
                        $query->where('nama', 'like', "%{$q}%");
                    })
                    ->orWhereHas('satuan', function ($query) use ($q) {
                        $query->where('satuan', 'like', "%{$q}%");
                    })
                    ->orWhereHas('satuanBesar', function ($query) use ($q) {
                        $query->where('satuan', 'like', "%{$q}%");
                    })
                    ->orWhereHas('industri', function ($query) use ($q) {
                        $query->where('nama_industri', 'like', "%{$q}%");
                    })
                    ->orWhereHas('golongan', function ($query) use ($q) {
                        $query->where('nama', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->filled('limit')) {
            $limit = $request->limit;
            $data = $query->paginate($limit);
        } else {
            $data = $query->paginate(15);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data Barang retrieved successfully',
            'data' => $data
        ]);
    }

    public function export(Request $request)
    {
        $fileName = 'data-barang-' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['Kode Barang', 'Nama Barang', 'Satuan Kecil', 'Satuan Besar', 'Kategori', 'Jenis', 'Golongan', 'Industri', 'Kapasitas', 'Letak Barang', 'Stok Minimal', 'Expire', 'Harga Dasar', 'H. Beli'];

        $callback = function () use ($request, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $query = DataBarang::query()
                ->with(['kategori', 'jenis', 'satuan', 'satuanBesar', 'industri', 'golongan'])
                ->where('status', '<>', '0')
                ->orderBy('nama_brng');

            if ($request->has('q')) {
                $q = $request->q;
                $query->where(function ($query) use ($q) {
                    $query->where('kode_brng', 'like', "%{$q}%")
                        ->orWhere('nama_brng', 'like', "%{$q}%")
                        ->orWhere('kapasitas', 'like', "%{$q}%")
                        ->orWhere('letak_barang', 'like', "%{$q}%")
                        ->orWhere('expire', 'like', "%{$q}%")
                        ->orWhereHas('kategori', function ($query) use ($q) {
                            $query->where('nama', 'like', "%{$q}%");
                        })
                        ->orWhereHas('jenis', function ($query) use ($q) {
                            $query->where('nama', 'like', "%{$q}%");
                        })
                        ->orWhereHas('satuan', function ($query) use ($q) {
                            $query->where('satuan', 'like', "%{$q}%");
                        })
                        ->orWhereHas('satuanBesar', function ($query) use ($q) {
                            $query->where('satuan', 'like', "%{$q}%");
                        })
                        ->orWhereHas('industri', function ($query) use ($q) {
                            $query->where('nama_industri', 'like', "%{$q}%");
                        })
                        ->orWhereHas('golongan', function ($query) use ($q) {
                            $query->where('nama', 'like', "%{$q}%");
                        });
                });
            }

            // Chunking for performance
            $query->chunk(1000, function ($items) use ($file) {
                foreach ($items as $item) {
                    fputcsv($file, [
                        $item->kode_brng,
                        $item->nama_brng,
                        $item->satuan ? $item->satuan->satuan : $item->kode_sat,
                        $item->satuanBesar ? $item->satuanBesar->satuan : $item->kode_satbesar,
                        $item->kategori ? $item->kategori->nama : $item->kode_kategori,
                        $item->jenis ? $item->jenis->nama : $item->kdjns,
                        $item->golongan ? $item->golongan->nama : $item->kode_golongan,
                        $item->industri ? $item->industri->nama_industri : $item->kode_industri,
                        $item->kapasitas,
                        $item->letak_barang,
                        $item->stokminimal,
                        $item->expire,
                        $item->dasar,
                        $item->h_beli
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'kode_brng' => 'required|unique:databarang,kode_brng',
            'nama_brng' => 'required',
            'kode_sat' => 'required',
            'dasar' => 'required|numeric',
            'h_beli' => 'required|numeric',
            'ralan' => 'required|numeric',
            'stokminimal' => 'required|numeric',
            'kapasitas' => 'required|numeric',
            'isi' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        try {
            $dataInputs = $request->except(['kategori', 'jenis', 'satuan', 'satuanBesar', 'industri', 'golongan', 'satuan_besar']);
            $dataInputs['status'] = '1';
            $data = DataBarang::create($dataInputs);
            return response()->json(['success' => true, 'message' => 'Data berhasil disimpan', 'data' => $data], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $kode_brng)
    {
        $barang = DataBarang::find($kode_brng);
        if (!$barang) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        try {
            $barang->update($request->except(['kategori', 'jenis', 'satuan', 'satuanBesar', 'industri', 'golongan', 'satuan_besar']));
            return response()->json(['success' => true, 'message' => 'Data berhasil diperbarui', 'data' => $barang]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($kode_brng)
    {
        $barang = DataBarang::find($kode_brng);
        if (!$barang) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        try {
            // Soft delete by setting status to '0'
            $barang->update(['status' => '0']);
            return response()->json(['success' => true, 'message' => 'Data berhasil dihapus (non-aktif)']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data', 'error' => $e->getMessage()], 500);
        }
    }

    public function restore($kode_brng)
    {
        $barang = DataBarang::find($kode_brng);
        if (!$barang) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        try {
            $barang->update(['status' => '1']);
            return response()->json(['success' => true, 'message' => 'Data berhasil diaktifkan kembali']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengaktifkan data', 'error' => $e->getMessage()], 500);
        }
    }

    public function attributes()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'kategori' => \App\Models\KategoriBarang::select('kode', 'nama')->get(),
                'jenis' => \App\Models\Jenis::select('kdjns', 'nama')->get(),
                'satuan' => \App\Models\KodeSatuan::select('kode_sat', 'satuan')->get(),
                'industri' => \App\Models\IndustriFarmasi::select('kode_industri', 'nama_industri')->get(),
                'golongan' => \App\Models\GolonganBarang::select('kode', 'nama')->get(),
            ]
        ]);
    }

    public function nextCode()
    {
        // Get the last code starting with 'B' (assuming B is the prefix for Barang)
        $lastData = DataBarang::where('kode_brng', 'like', 'B%')
            ->orderBy('kode_brng', 'desc')
            ->first();

        if ($lastData) {
            $lastCode = $lastData->kode_brng;
            // Extract the number part (remove 'B')
            $number = intval(substr($lastCode, 1));
            $nextNumber = $number + 1;
        } else {
            $nextNumber = 1;
        }

        // Format: B + 14 digits (or however long it needs to be check existing B000000397 is 10 chars total, so 9 digits)
        // B000000397 -> B + 9 digits
        $nextCode = 'B' . str_pad($nextNumber, 9, '0', STR_PAD_LEFT);

        return response()->json([
            'success' => true,
            'code' => $nextCode
        ]);
    }
}
