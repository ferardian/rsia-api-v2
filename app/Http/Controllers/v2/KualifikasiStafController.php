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
                ->leftJoin('departemen as d', 'd.dep_id', '=', 'p.departemen')
                ->where('p.stts_aktif', 'AKTIF')
                ->select([
                    'p.nik',
                    'p.nama',
                    'p.jbtn',
                    'd.nama as departemen',
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
                    'k.bukti_kelulusan',
                    'k.status',
                    'k.tgl_update',
                    // Latest Credential SK data
                    'sk.judul as judul_sk',
                    'sk.tgl_terbit as tgl_terbit_sk',
                    'sk.berkas as berkas_sk',
                    // Flag to check if kualifikasi exists
                    \DB::raw('IF(k.nik IS NOT NULL, 1, 0) as has_kualifikasi')
                ])
                ->leftJoin(\DB::raw('(SELECT nik, judul, tgl_terbit, berkas 
                            FROM rsia_sk 
                            WHERE status_approval = "disetujui" 
                            AND (nik, tgl_terbit) IN (SELECT nik, MAX(tgl_terbit) FROM rsia_sk WHERE status_approval = "disetujui" GROUP BY nik)
                           ) as sk'), 'sk.nik', '=', 'p.nik');

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

            if ($request->filled('group')) {
                if ($request->group === 'perawat_ners') {
                    $query->where(function($q) {
                        $q->where('p.pendidikan', 'like', '%Perawat%')
                          ->orWhere('p.pendidikan', 'like', '%Ners%')
                          ->orWhere('k.prodi', 'like', '%Perawat%')
                          ->orWhere('k.prodi', 'like', '%Ners%')
                          ->orWhere('k.kategori_profesi', 'like', '%Perawat%')
                          ->orWhere('k.kategori_profesi', 'like', '%Ners%');
                    });
                }
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
     * Upload Bukti Kelulusan
     *
     * @param Request $request
     * @param string $nik
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadBuktiKelulusan(Request $request, $nik)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240' // max 10MB
            ]);

            $kualifikasi = RsiaKualifikasiStafKlinis::where('nik', $nik)->first();
            $pegawai = Pegawai::where('nik', $nik)->first();

            if (!$kualifikasi || !$pegawai) {
                return \App\Helpers\ApiResponse::notFound('Data Kualifikasi atau Pegawai tidak ditemukan');
            }

            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            
            // Format file name: NIK-Bukti-Kelulusan-NamaPegawai.ext
            $nik_formatted = str_replace('.', '-', $nik);
            $nama_pegawai_formatted = str_replace([' ', '/', '\\'], '-', strtoupper($pegawai->nama));
            $nama_pegawai_formatted = preg_replace('/[^A-Z0-9\-]/', '-', $nama_pegawai_formatted);
            $nama_pegawai_formatted = preg_replace('/-+/', '-', $nama_pegawai_formatted);
            $nama_pegawai_formatted = trim($nama_pegawai_formatted, '-');

            $file_name = $nik_formatted . '-BUKTI-KELULUSAN-' . $nama_pegawai_formatted . '.' . $extension;

            \DB::transaction(function () use ($kualifikasi, $file, $file_name) {
                $oldFile = $kualifikasi->bukti_kelulusan;
                $st = new \Illuminate\Support\Facades\Storage();
                $location = env('DOCUMENT_KUALIFIKASI_SAVE_LOCATION', 'webapps/rsia_kualifikasi/');
                
                if ($location && !\Illuminate\Support\Str::endsWith($location, '/')) {
                    $location .= '/';
                }

                // Delete old file if exists
                if ($oldFile && $oldFile != '' && $st::disk('sftp_pegawai')->exists($location . $oldFile)) {
                    \App\Helpers\Logger\RSIALogger::berkas("DELETING OLD BUKTI KELULUSAN", 'info', ['file_name' => $oldFile]);
                    $st::disk('sftp_pegawai')->delete($location . $oldFile);
                }

                // Upload new file
                $st::disk('sftp_pegawai')->put($location . $file_name, file_get_contents($file));
                \App\Helpers\Logger\RSIALogger::berkas("UPLOADED BUKTI KELULUSAN", 'info', ['file_name' => $file_name, 'file_size' => $file->getSize()]);

                // Update database
                $kualifikasi->update(['bukti_kelulusan' => $file_name, 'tgl_update' => now()]);
            });

            return \App\Helpers\ApiResponse::success('Bukti Kelulusan berhasil diupload', ['file' => $file_name]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \App\Helpers\Logger\RSIALogger::berkas("UPLOAD BUKTI KELULUSAN FAILED", 'error', ['nik' => $nik, 'error' => $e->getMessage()]);
            return \App\Helpers\ApiResponse::error('Gagal mengupload bukti kelulusan', 'upload_failed', $e->getMessage(), 500);
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
                // Delete file from SFTP if exists
                if ($kualifikasi->bukti_kelulusan) {
                    $st = new \Illuminate\Support\Facades\Storage();
                    $location = env('DOCUMENT_KUALIFIKASI_SAVE_LOCATION', 'webapps/rsia_kualifikasi/');
                    if ($location && !\Illuminate\Support\Str::endsWith($location, '/')) {
                        $location .= '/';
                    }
                    if ($st::disk('sftp_pegawai')->exists($location . $kualifikasi->bukti_kelulusan)) {
                        \App\Helpers\Logger\RSIALogger::berkas("DELETING BUKTI KELULUSAN ON DESTROY", 'info', ['file_name' => $kualifikasi->bukti_kelulusan]);
                        $st::disk('sftp_pegawai')->delete($location . $kualifikasi->bukti_kelulusan);
                    }
                }

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
