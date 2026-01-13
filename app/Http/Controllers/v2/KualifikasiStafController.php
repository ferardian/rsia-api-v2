<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RsiaKualifikasiStafKlinis;
use App\Models\Pegawai;
use App\Traits\LogsToTracker;

class KualifikasiStafController extends Controller
{
    use LogsToTracker;

    /**
     * Get list of pegawai eligible for kualifikasi staf klinis
     * Shows all pegawai with petugas, jabatan, and pendidikan_str relations
     * Includes kualifikasi status (has_kualifikasi flag)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Get pegawai with relations to petugas, jabatan, and rsia_pendidikan_str
            $query = \DB::table('pegawai as p')
                ->leftJoin('petugas as pt', 'pt.nip', '=', 'p.nik')
                ->leftJoin('rsia_pendidikan_str as ps', 'ps.kode_tingkat', '=', 'p.pendidikan')
                ->leftJoin('rsia_kualifikasi_staf_klinis as k', 'k.nik', '=', 'p.nik')
                ->where('p.stts_aktif', 'AKTIF')
                ->select([
                    'p.nik',
                    'p.nama',
                    'p.jbtn',
                    'p.pendidikan',
                    'pt.no_telp',
                    // Kualifikasi data (will be null if not exists)
                    'k.kategori_profesi',
                    'k.nomor_str',
                    'k.tanggal_str',
                    'k.tanggal_akhir_str',
                    'k.nomor_sip',
                    'k.tanggal_izin_praktek',
                    'k.perguruan_tinggi',
                    'k.prodi',
                    'k.tanggal_lulus',
                    'k.status',
                    'k.tgl_update',
                    // Flag to check if kualifikasi exists
                    \DB::raw('IF(k.nik IS NOT NULL, 1, 0) as has_kualifikasi')
                ]);

            // Apply filters
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('p.nik', 'like', "%{$search}%")
                      ->orWhere('p.nama', 'like', "%{$search}%")
                      ->orWhere('p.jbtn', 'like', "%{$search}%")
                      ->orWhere('k.nomor_str', 'like', "%{$search}%")
                      ->orWhere('k.nomor_sip', 'like', "%{$search}%");
                });
            }

            // Filter by logged in user department
            $user = $request->user();
            if ($request->boolean('filter_user_dep') && $user && $user->detail) {
                $dept = $user->detail->departemen;
                // Filter strict sesuai request user: hanya tampilkan departemen user login
                if ($dept && $dept !== '-') {
                    $query->where('p.departemen', $dept);
                }
            }

            // Tambahan: Filter hanya pegawai yang pendidikannya ada di master rsia_pendidikan_str
            // Artinya hanya Nakes yang wajib STR/SIP yang tampil di menu ini (Global Filter)
            $query->whereNotNull('ps.kode_tingkat');

            if ($request->filled('kategori_profesi')) {
                $query->where('k.kategori_profesi', $request->kategori_profesi);
            }

            if ($request->filled('has_kualifikasi')) {
                if ($request->has_kualifikasi == '1') {
                    $query->whereNotNull('k.nik');
                } elseif ($request->has_kualifikasi == '0') {
                    $query->whereNull('k.nik');
                }
            }
            // Apply sorting
            $sortBy = $request->get('sort_by', 'p.nama');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            $data = $query->get();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get kualifikasi by NIK
     *
     * @param string $nik
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($nik)
    {
        try {
            $kualifikasi = RsiaKualifikasiStafKlinis::where('nik', $nik)
                ->with('pegawai:nik,nama')
                ->first();

            if (!$kualifikasi) {
                return \App\Helpers\ApiResponse::notFound('Kualifikasi not found');
            }

            return \App\Helpers\ApiResponse::success('Data retrieved successfully', $kualifikasi);

        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to retrieve data', 'show_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Create new kualifikasi
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nik' => 'required|string|exists:pegawai,nik',
                'kategori_profesi' => 'required|in:Staf Medis,Staf Kebidanan,Staf Keperawatan,Staf Klinis Lainnya',
                'nomor_str' => 'required|string|max:50',
                'tanggal_str' => 'required|date',
                'tanggal_akhir_str' => 'required|date',
                'nomor_sip' => 'required|string|max:50',
                'tanggal_izin_praktek' => 'required|date',
                'perguruan_tinggi' => 'required|string|max:100',
                'prodi' => 'required|string|max:100',
                'tanggal_lulus' => 'required|date',
                'status' => 'nullable|integer|in:0,1'
            ]);

            // Check if already exists
            $existing = RsiaKualifikasiStafKlinis::where('nik', $request->nik)->first();
            if ($existing) {
                return \App\Helpers\ApiResponse::error('Kualifikasi sudah ada untuk NIK ini', 'duplicate_entry', null, 422);
            }

            \DB::transaction(function () use ($request) {
                $data = $request->all();
                $data['status'] = $request->get('status', 1);
                $data['tgl_update'] = now();

                RsiaKualifikasiStafKlinis::create($data);

                $sql = "INSERT INTO rsia_kualifikasi_staf_klinis (nik, kategori_profesi, nomor_str) VALUES ('{$request->nik}', '{$request->kategori_profesi}', '{$request->nomor_str}')";
                $this->logTracker($sql, $request);
            });

            return \App\Helpers\ApiResponse::success('Data saved successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to save data', 'store_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Update kualifikasi
     *
     * @param Request $request
     * @param string $nik
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $nik)
    {
        try {
            $request->validate([
                'kategori_profesi' => 'required|in:Staf Medis,Staf Kebidanan,Staf Keperawatan,Staf Klinis Lainnya',
                'nomor_str' => 'required|string|max:50',
                'tanggal_str' => 'required|date',
                'tanggal_akhir_str' => 'required|date',
                'nomor_sip' => 'required|string|max:50',
                'tanggal_izin_praktek' => 'required|date',
                'perguruan_tinggi' => 'required|string|max:100',
                'prodi' => 'required|string|max:100',
                'tanggal_lulus' => 'required|date',
                'status' => 'nullable|integer|in:0,1'
            ]);

            $kualifikasi = RsiaKualifikasiStafKlinis::where('nik', $nik)->first();
            if (!$kualifikasi) {
                return \App\Helpers\ApiResponse::notFound('Kualifikasi not found');
            }

            \DB::transaction(function () use ($request, $kualifikasi, $nik) {
                $data = $request->all();
                $data['tgl_update'] = now();

                $kualifikasi->update($data);

                $sql = "UPDATE rsia_kualifikasi_staf_klinis SET kategori_profesi='{$request->kategori_profesi}', nomor_str='{$request->nomor_str}' WHERE nik='{$nik}'";
                $this->logTracker($sql, $request);
            });

            return \App\Helpers\ApiResponse::success('Data updated successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to update data', 'update_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Delete kualifikasi
     *
     * @param string $nik
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($nik)
    {
        try {
            $kualifikasi = RsiaKualifikasiStafKlinis::where('nik', $nik)->first();
            if (!$kualifikasi) {
                return \App\Helpers\ApiResponse::notFound('Kualifikasi not found');
            }

            \DB::transaction(function () use ($kualifikasi, $nik) {
                $kualifikasi->delete();

                $sql = "DELETE FROM rsia_kualifikasi_staf_klinis WHERE nik='{$nik}'";
                $this->logTracker($sql, request());
            });

            return \App\Helpers\ApiResponse::success('Data deleted successfully');

        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to delete data', 'delete_failed', $e->getMessage(), 500);
        }
    }
}
