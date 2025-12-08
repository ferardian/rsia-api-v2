<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\GambarRadiologi;
use App\Models\PeriksaRadiologi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GambarRadiologiController extends Controller
{
    /**
     * Mendapatkan daftar gambar radiologi berdasarkan nomor rawat.
     *
     * @param string $no_rawat
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(string $no_rawat): JsonResponse
    {
        try {
            // Validasi input
            if (empty($no_rawat)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor rawat harus diisi'
                ], 400);
            }

            // Ambil data gambar radiologi dengan relasi yang diperlukan
            $gambarRadiologi = GambarRadiologi::with(['periksaRadiologi' => function($query) {
                $query->select('no_rawat', 'kd_jenis_prw', 'tgl_periksa', 'jam')
                      ->with('jenisPerawatan');
            }, 'petugas'])
                ->whereNoRawat($no_rawat)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $gambarRadiologi
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mendapatkan detail gambar radiologi berdasarkan composite key.
     *
     * @param string $no_rawat
     * @param string $tgl_periksa
     * @param string $jam
     * @param string $lokasi_gambar
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $no_rawat, string $tgl_periksa, string $jam, string $lokasi_gambar): JsonResponse
    {
        try {
            // Validasi input
            if (empty($no_rawat) || empty($tgl_periksa) || empty($jam) || empty($lokasi_gambar)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak lengkap'
                ], 400);
            }

            // Ambil data gambar radiologi spesifik
            $gambar = GambarRadiologi::with(['periksaRadiologi' => function($query) {
                    $query->select('no_rawat', 'kd_jenis_prw', 'tgl_periksa', 'jam')
                          ->with('jenisPerawatan');
                }, 'petugas'])
                ->whereNoRawat($no_rawat)
                ->whereTglPeriksa($tgl_periksa)
                ->whereJam($jam)
                ->whereLokasiGambar($lokasi_gambar)
                ->first();

            if (!$gambar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data gambar tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $gambar
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menambahkan gambar radiologi baru.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validated = $request->validate([
                'no_rawat' => 'required|string|max:15',
                'kd_jenis_prw' => 'required|string|max:10',
                'tgl_periksa' => 'required|date',
                'jam' => 'required|date_format:H:i:s',
                'lokasi_gambar' => 'required|string|max:500',
                'nip' => 'nullable|string|max:20'
            ]);

            // Cek apakah periksa radiologi sudah ada
            $periksaRadiologi = PeriksaRadiologi::where([
                'no_rawat' => $validated['no_rawat'],
                'kd_jenis_prw' => $validated['kd_jenis_prw'],
                'tgl_periksa' => $validated['tgl_periksa'],
                'jam' => $validated['jam']
            ])->first();

            if (!$periksaRadiologi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data periksa radiologi tidak ditemukan'
                ], 404);
            }

            // Create primary key untuk GambarRadiologi
            $primaryKey = [
                'no_rawat' => $validated['no_rawat'],
                'tgl_periksa' => $validated['tgl_periksa'],
                'jam' => $validated['jam'],
                'lokasi_gambar' => $validated['lokasi_gambar']
            ];

            // Tambahkan field tambahan
            $validated['created_at'] = now();
            $validated['updated_at'] = now();

            // Gunakan firstOrCreate dengan composite key
            $gambar = GambarRadiologi::updateOrCreate($primaryKey, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Gambar radiologi berhasil disimpan',
                'data' => $gambar->load(['periksaRadiologi', 'petugas'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengupdate gambar radiologi.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        try {
            // Validasi input
            $validated = $request->validate([
                'no_rawat' => 'required|string|max:15',
                'kd_jenis_prw' => 'required|string|max:10',
                'tgl_periksa' => 'required|date',
                'jam' => 'required|date_format:H:i:s',
                'lokasi_gambar' => 'required|string|max:500',
                'nip' => 'nullable|string|max:20'
            ]);

            // Create primary key untuk mencari
            $primaryKey = [
                'no_rawat' => $validated['no_rawat'],
                'tgl_periksa' => $validated['tgl_periksa'],
                'jam' => $validated['jam'],
                'lokasi_gambar' => $validated['lokasi_gambar']
            ];

            $gambar = GambarRadiologi::where($primaryKey)->first();

            if (!$gambar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data gambar tidak ditemukan'
                ], 404);
            }

            // Update field tambahan
            $validated['updated_at'] = now();

            $gambar->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Gambar radiologi berhasil diupdate',
                'data' => $gambar->load(['periksaRadiologi', 'petugas'])
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menghapus gambar radiologi.
     *
     * @param string $no_rawat
     * @param string $tgl_periksa
     * @param string $jam
     * @param string $lokasi_gambar
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $no_rawat, string $tgl_periksa, string $jam, string $lokasi_gambar): JsonResponse
    {
        try {
            // Create primary key
            $primaryKey = [
                'no_rawat' => $no_rawat,
                'tgl_periksa' => $tgl_periksa,
                'jam' => $jam,
                'lokasi_gambar' => $lokasi_gambar
            ];

            $gambar = GambarRadiologi::where($primaryKey)->first();

            if (!$gambar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data gambar tidak ditemukan'
                ], 404);
            }

            $gambar->delete();

            return response()->json([
                'success' => true,
                'message' => 'Gambar radiologi berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}