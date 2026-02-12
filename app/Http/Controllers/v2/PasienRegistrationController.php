<?php

namespace App\Http\Controllers\v2;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class PasienRegistrationController extends Controller
{
    /**
     * Step 1: Check if NIK is already registered in the main patient table.
     */
    public function cekNik(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nik' => 'required|digits:16',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $pasien = DB::table('pasien')->where('no_ktp', $request->nik)->first();

        if ($pasien) {
            return ApiResponse::error(
                "NIK sudah terdaftar dengan No. RM {$pasien->no_rkm_medis}. Silakan login menggunakan No. RM tersebut.",
                "nik_already_registered",
                ['no_rkm_medis' => $pasien->no_rkm_medis],
                422
            );
        }

        return ApiResponse::success("NIK tersedia untuk pendaftaran baru.");
    }

    /**
     * Step 2: Send OTP via WhatsApp.
     */
    /**
     * Step 2: Send OTP via WhatsApp.
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_telp' => 'required|numeric|digits_between:10,14',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $phone = $this->formatPhoneNumber($request->no_telp);
        $ip = $request->ip();
        
        // Rate Limiting: Max 3 OTPs per IP per hour
        $key = 'send_otp:' . $ip . ':' . $phone;
        // echo "   🔍 Rate Key: $key\n"; // DEBUG
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return ApiResponse::error(
                "Terlalu banyak permintaan OTP. Silakan coba lagi dalam $seconds detik.",
                "rate_limit_exceeded",
                null,
                429
            );
        }
        RateLimiter::hit($key, 3600); // 1 hour decay

        $otpCode = random_int(100000, 999999);
        
        // Store OTP in Cache for 5 minutes
        Cache::put('otp_' . $phone, $otpCode, 300);

        $message = "Kode OTP pendaftaran pasien baru RSIA Aisyiyah Pekajangan adalah: *$otpCode*\n\nJangan memberikan kode ini kepada siapapun. Kode berlaku selama 5 menit.";

        try {
            // Using the existing WhatsApp job
            if (!app()->environment('testing')) {
                \App\Jobs\SendWhatsApp::dispatch($phone, $message)
                    ->onQueue('otp');
            }

            // DEBUG
            // echo "ENV: " . app()->environment() . " | DEBUG: " . config('app.debug') . "\n";

            return ApiResponse::success('OTP sent successfully', [
                'otp' => (config('app.debug') || app()->environment('local', 'testing')) ? $otpCode : null 
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error("Gagal mengirim OTP: " . $e->getMessage(), "otp_send_failed", null, 500);
        }
    }

    /**
     * Step 3: Verify OTP and Generate Registration Token
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_telp' => 'required',
            'otp'     => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $phone = $this->formatPhoneNumber($request->no_telp);
        $cachedOtp = Cache::get('otp_' . $phone);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
             return ApiResponse::error("Kode OTP tidak valid atau sudah kadaluarsa.", "invalid_otp", null, 400);
        }

        // OTP Valid: Generate Verification Token
        // This token verifies that THIS phone number has been verified.
        // Token valid for 15 minutes.
        $regToken = Str::random(60);
        Cache::put('reg_token_' . $regToken, $phone, 900); // 15 mins
        
        // Clear used OTP
        Cache::forget('otp_' . $phone);

        return ApiResponse::success('Verifikasi berhasil.', [
            'token'   => $regToken
        ]);
    }

    /**
     * Step 4: Final Registration.
     */
    public function register(Request $request)
    {
        Log::info("🚀 [Register] Incoming request from IP: " . $request->ip(), [
            'nik' => $request->nik,
            'nm_pasien' => $request->nm_pasien,
            'has_file' => $request->hasFile('ktp_image'),
        ]);

        // 0. Security Check: Rate Limiting & Token Validity
        Log::info("🚀 [Register] Step: Security Check");
        $ip = $request->ip();
        $rateKey = 'register_pasien:' . $ip;
        
        if (RateLimiter::tooManyAttempts($rateKey, 3)) { // Max 3 registrations per hour per IP
            return ApiResponse::error("Batas pendaftaran tercapai. Silakan coba lagi nanti.", "rate_limit_exceeded", null, 429);
        }

        $validator = Validator::make($request->all(), [
            'reg_token' => 'required|string', // MUST have token from verifyOtp
            'nik'       => 'required|digits:16',
            'nm_pasien' => 'required|string|max:40',
            'jk'        => 'required|in:L,P',
            'tmp_lahir' => 'required|string|max:15',
            'tgl_lahir' => 'required|date',
            'nm_ibu'    => 'required|string|max:40',
            'alamat'    => 'required|string|max:200',
            'no_telp'   => 'required|string|max:40',
            'email'     => 'nullable|email|max:50',
            
            // Addressing (Optional, defaults to '-')
            'kd_kel'    => 'nullable|integer',
            'kd_kec'    => 'nullable|integer',
            'kd_kab'    => 'nullable|integer',
            'kd_prop'   => 'nullable|integer',
            
            // Responsible Person (PJ)
            'namakeluarga' => 'required|string|max:50',
            'keluarga'     => 'required|in:AYAH,IBU,SUAMI,ISTRI,SAUDARA,ANAK',
            'pekerjaanpj'  => 'nullable|string|max:35',
            'alamatpj'     => 'nullable|string|max:100',

            // File Upload
            'ktp_image'    => 'required|image|mimes:jpg,jpeg,png|max:2048', // Max 2MB
        ]);

        Log::info("🚀 [Register] Step: Validating Fields");
        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        // Verify Token
        Log::info("🚀 [Register] Step: Verifying Token with Cache");
        $verifiedPhone = Cache::get('reg_token_' . $request->reg_token);
        if (!$verifiedPhone) {
            return ApiResponse::error("Sesi pendaftaran tidak valid atau kadaluarsa. Silakan ulangi verifikasi OTP.", "invalid_token", null, 401);
        }

        // Optional: Ensure phone number matches (if we want to be strict)
        // $requestPhone = $this->formatPhoneNumber($request->no_telp);
        // if ($verifiedPhone != $requestPhone) { ... }
        // For now, we trust the token implies a verified user is acting.

        // Increment Rate Limit
        RateLimiter::hit($rateKey, 3600);

        // Final guard: Check NIK in main table
        Log::info("🚀 [Register] Step: Checking NIK in Database (exists?)");
        if (DB::table('pasien')->where('no_ktp', $request->nik)->exists()) {
            return ApiResponse::error("NIK sudah terdaftar.", "nik_already_registered", null, 422);
        }

        // Handle KTP Image Upload via SFTP
        Log::info("🚀 [Register] Starting SFTP upload to: " . env('SFTP_HOST'));
        $ktpPath = null;
        if ($request->hasFile('ktp_image')) {
            try {
                $file = $request->file('ktp_image');
                $filename = 'ktp_' . $request->nik . '.' . $file->getClientOriginalExtension();
                $saveDir = env('FOTO_KTP_SAVE_LOCATION', 'pasien_ktp/');
                
                // Store on SFTP
                $ktpPath = $saveDir . $filename;
                
                // Ensure disk is sftp
                if (!Storage::disk('sftp')->put($ktpPath, file_get_contents($file))) {
                    throw new \Exception("Gagal mengunggah foto KTP ke server file.");
                }
            } catch (\Exception $e) {
                return ApiResponse::error("Gagal memproses foto KTP: " . $e->getMessage(), "ktp_upload_failed", null, 500);
            }
        }

        try {
            Log::info("🚀 [Register] Starting Database Transaction");
            DB::beginTransaction();

            // 1. Generate No. RM (Medical Record Number)
            // Logic: Ambil last RM dari set_no_rkm_medis, lalu +1
            $setNoRm = DB::table('set_no_rkm_medis')->lockForUpdate()->first();
            if (!$setNoRm) {
                throw new \Exception("Configurasi Medical Record (set_no_rkm_medis) tidak ditemukan.");
            }
            // Increment BEFORE using
            $noRkmMedis = str_pad((int)$setNoRm->no_rkm_medis + 1, 6, '0', STR_PAD_LEFT);

            // 2. Calculate Umur (e.g., "30 Th 5 Bl 10 Hr")
            $birthDate = new \DateTime($request->tgl_lahir);
            $now = new \DateTime();
            $interval = $now->diff($birthDate);
            $umur = "{$interval->y} Th {$interval->m} Bl {$interval->d} Hr";

            // 3. Fetch Area Names (Kelurahan, Kecamatan, Kabupaten, Propinsi) for PJ fields
            // Defaults to '-' (ID: 1 based on check) if not provided
            $kdKel = $request->kd_kel ?? 1; // Default to '-'
            $kdKec = $request->kd_kec ?? 1; // Default to '-'
            $kdKab = $request->kd_kab ?? 1; // Default to '-'
            $kdProp = $request->kd_prop ?? 1; // Default to '-'

            $kel = DB::table('kelurahan')->where('kd_kel', $kdKel)->first();
            $kec = DB::table('kecamatan')->where('kd_kec', $kdKec)->first();
            $kab = DB::table('kabupaten')->where('kd_kab', $kdKab)->first();
            $prop = DB::table('propinsi')->where('kd_prop', $kdProp)->first();

            // 4. Direct Insert into PASIEN table
            DB::table('pasien')->insert([
                'no_rkm_medis' => $noRkmMedis,
                'nm_pasien'    => strtoupper($request->nm_pasien),
                'no_ktp'       => $request->nik,
                'jk'           => $request->jk,
                'tmp_lahir'    => strtoupper($request->tmp_lahir),
                'tgl_lahir'    => $request->tgl_lahir,
                'nm_ibu'       => strtoupper($request->nm_ibu),
                'alamat'       => strtoupper($request->alamat),
                'gol_darah'    => '-',
                'pekerjaan'    => '-',
                'stts_nikah'   => 'BELUM MENIKAH',
                'agama'        => 'ISLAM',
                'tgl_daftar'   => now()->format('Y-m-d'),
                'no_tlp'       => $request->no_telp,
                'umur'         => $umur,
                'pnd'          => '-',
                'keluarga'     => $request->keluarga,
                'namakeluarga' => strtoupper($request->namakeluarga),
                'kd_pj'        => 'A01', // Default: UMUM
                'no_peserta'   => '-',
                'kd_kel'       => $kdKel,
                'kd_kec'       => $kdKec,
                'kd_kab'       => $kdKab,
                'pekerjaanpj'  => strtoupper($request->pekerjaanpj ?? '-'),
                'alamatpj'     => strtoupper($request->alamatpj ?? $request->alamat),
                'kelurahanpj'  => $kel ? strtoupper($kel->nm_kel) : '-',
                'kecamatanpj'  => $kec ? strtoupper($kec->nm_kec) : '-',
                'kabupatenpj'  => $kab ? strtoupper($kab->nm_kab) : '-',
                'perusahaan_pasien' => '-',
                'suku_bangsa'  => 5, // Typical default value from analysis
                'bahasa_pasien' => 5, // Typical default value from analysis
                'cacat_fisik'  => 5, // Typical default value from analysis
                'email'        => $request->email ?? '',
                'nip'          => '',
                'kd_prop'      => $kdProp,
                'propinsipj'   => $prop ? strtoupper($prop->nm_prop) : '-',
            ]);

            // 5. Insert/Update into personal_pasien (Safe for SIMRS Khanza)
            // This table is used for mobile/e-pasien features.
            DB::table('personal_pasien')->updateOrInsert(
                ['no_rkm_medis' => $noRkmMedis],
                [
                    'foto_ktp' => $ktpPath,
                    // 'gambar'  => $ktpPath, // Optional: also set as profile photo? Decided no, keep separately.
                    // 'password' stays same if exists, or null here (usually set during first app login)
                ]
            );

            // 6. Update the RM counter (Set equal to the new RM used)
            DB::table('set_no_rkm_medis')->update(['no_rkm_medis' => $noRkmMedis]);

            DB::commit();

            // Success Notification via WhatsApp
            try {
                Log::info("🚀 [Register] Dispatching WhatsApp Job (Queue: " . env('QUEUE_CONNECTION') . ")");
                $phone = $this->formatPhoneNumber($request->no_telp);
                $successMessage = "Pendaftaran Pasien Baru Berhasil!\n\nNama: " . strtoupper($request->nm_pasien) . "\nNo. RM: *$noRkmMedis*\n\nHarap simpan No. RM ini untuk keperluan berobat. Terima kasih.";
                
                \App\Jobs\SendWhatsApp::dispatch($phone, $successMessage)->onQueue('otp');
            } catch (\Exception $e) {
                Log::error("Failed to send success WhatsApp: " . $e->getMessage());
            }

            // Invalidate Token
            Cache::forget('reg_token_' . $request->reg_token);

            return ApiResponse::success('Pendaftaran pasien baru berhasil.', [
                'no_rkm_medis' => $noRkmMedis,
                'nm_pasien'    => strtoupper($request->nm_pasien),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Registration Failed: " . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);
            return ApiResponse::error(
                "Gagal melakukan pendaftaran: " . $e->getMessage(),
                "registration_failed",
                $e->getTraceAsString(), 
                500
            );
        }
    }

    private function formatPhoneNumber($number)
    {
        return Str::startsWith($number, '0') ? '62' . substr($number, 1) : $number;
    }
}
