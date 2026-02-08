<?php

namespace App\Http\Controllers\v2;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Pasien;
use App\Models\RsiaKeluargaPasien;
use Illuminate\Http\Request;

class RsiaKeluargaPasienController extends Controller
{
    public function index(Request $request)
    {
        // Validasi input no_rkm_medis_master dari request atau user login
        // Karena ini endpoint API, kita asumsikan user kirim no_rkm_medis nya
        // Atau ambil dari user yang sedang login jika ada relasi user->pasien
        
        $user = $request->user();
        if (!$user) {
             return ApiResponse::error('Unauthorized', 'unauthorized', null, 401);
        }

        // Asumsi user table punya kolom no_rkm_medis, atau kita perlu cara lain detect no_rm user
        // Tapi berdasarkan diskusi sebelumnya, kita pakai no_rkm_medis_master sebagai key
        // Jadi kita filter berdasarkan no_rkm_medis milik user ybs.
        
        $noRkmMedisMaster = $user->no_rkm_medis; 

        if (!$noRkmMedisMaster) {
            return ApiResponse::error('User tidak terhubung dengan data pasien', 'user_no_patient', null, 400);
        }

        $keluarga = RsiaKeluargaPasien::with(['keluarga' => function($q) {
            $q->select('no_rkm_medis', 'nm_pasien', 'jk', 'tgl_lahir', 'no_ktp');
        }])
        ->where('no_rkm_medis_master', $noRkmMedisMaster)
        ->get();

        return ApiResponse::success($keluarga);
    }

    public function store(Request $request)
    {
        $request->validate([
            'no_rkm_medis_keluarga' => 'required|exists:pasien,no_rkm_medis',
            'tgl_lahir_keluarga'    => 'required|date_format:Y-m-d', // Format verification
            'hubungan'              => 'required|string',
        ]);

        $user = $request->user();
        $noRkmMedisMaster = $user->no_rkm_medis;

        if (!$noRkmMedisMaster) {
            return ApiResponse::error('User tidak terhubung dengan data pasien', 'user_no_patient', null, 400);
        }

        // 1. Cek apakah pasien keluarga valid (Verifikasi Tanggal Lahir)
        $pasienKeluarga = Pasien::where('no_rkm_medis', $request->no_rkm_medis_keluarga)->first();

        if (!$pasienKeluarga) {
             return ApiResponse::error('Pasien tidak ditemukan', 'patient_not_found', null, 404);
        }

        if ($pasienKeluarga->tgl_lahir !== $request->tgl_lahir_keluarga) {
            return ApiResponse::error('Verifikasi Gagal: Tanggal lahir tidak cocok dengan data pasien.', 'verification_failed', null, 400);
        }
        
        // 2. Cek apakah sudah ada (Prevent Duplicate)
        $exists = RsiaKeluargaPasien::where('no_rkm_medis_master', $noRkmMedisMaster)
            ->where('no_rkm_medis_keluarga', $request->no_rkm_medis_keluarga)
            ->exists();
            
        if ($exists) {
            return ApiResponse::error('Anggota keluarga ini sudah terdaftar', 'duplicate_entry', null, 400);
        }
        
        // 3. Prevent self-add
        if ($noRkmMedisMaster === $request->no_rkm_medis_keluarga) {
             return ApiResponse::error('Tidak bisa menambahkan diri sendiri sebagai anggota keluarga', 'self_add_error', null, 400);
        }

        // 4. Create Relation
        $keluarga = RsiaKeluargaPasien::create([
            'no_rkm_medis_master'   => $noRkmMedisMaster,
            'no_rkm_medis_keluarga' => $request->no_rkm_medis_keluarga,
            'hubungan'              => $request->hubungan,
        ]);

        return ApiResponse::success($keluarga, 'Anggota keluarga berhasil ditambahkan');
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'no_rkm_medis_keluarga' => 'required',
        ]);

        $user = $request->user();
        $noRkmMedisMaster = $user->no_rkm_medis;

        $delete = RsiaKeluargaPasien::where('no_rkm_medis_master', $noRkmMedisMaster)
            ->where('no_rkm_medis_keluarga', $request->no_rkm_medis_keluarga)
            ->delete();

        if ($delete) {
            return ApiResponse::success(null, 'Anggota keluarga berhasil dihapus');
        } else {
            return ApiResponse::error('Gagal menghapus anggota keluarga / data tidak ditemukan', 'delete_failed', null, 400);
        }
    }
}
