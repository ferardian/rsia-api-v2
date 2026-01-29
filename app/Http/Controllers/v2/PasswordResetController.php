<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Pegawai;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Generate Captcha
     */
    public function captcha()
    {
        $code = Str::upper(Str::random(6));
        $id = Str::uuid()->toString();

        Cache::put('captcha_' . $id, $code, now()->addMinutes(10));

        // Create image
        $width = 150;
        $height = 50;
        $image = imagecreatetruecolor($width, $height);

        // Colors
        $bg = imagecolorallocate($image, 255, 255, 255);
        $text_color = imagecolorallocate($image, 0, 0, 0);
        $noise_color = imagecolorallocate($image, 100, 100, 100);

        imagefilledrectangle($image, 0, 0, $width, $height, $bg);

        // Add some noise
        for ($i = 0; $i < 50; $i++) {
            imagesetpixel($image, rand(0, $width), rand(0, $height), $noise_color);
        }

        // Add text
        $font = 5; // Internal PHP font
        $x = ($width - (imagefontwidth($font) * strlen($code))) / 2;
        $y = ($height - imagefontheight($font)) / 2;
        imagestring($image, $font, $x, $y, $code, $text_color);

        ob_start();
        imagepng($image);
        $image_data = ob_get_clean();
        imagedestroy($image);

        return response()->json([
            'success' => true,
            'data' => [
                'captcha_id' => $id,
                'captcha_img' => 'data:image/png;base64,' . base64_encode($image_data)
            ]
        ]);
    }

    /**
     * Request Password Reset
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'email' => 'required|email',
            'captcha_id' => 'required',
            'captcha_code' => 'required'
        ]);

        // Validate Captcha
        $cachedCode = Cache::get('captcha_' . $request->captcha_id);
        if (!$cachedCode || strtoupper($request->captcha_code) !== $cachedCode) {
            return ApiResponse::error('Captcha tidak valid', 'validation_error', null, 422);
        }
        Cache::forget('captcha_' . $request->captcha_id);

        // Find user & validate email
        $user = User::where('id_user', DB::raw("AES_ENCRYPT('{$request->username}', '" . env('MYSQL_AES_KEY_IDUSER') . "')"))->first();

        if (!$user) {
            return ApiResponse::error('Username tidak ditemukan', 'not_found', null, 404);
        }

        $pegawai = Pegawai::with('email')->where('nik', $request->username)->first();
        if (!$pegawai || !$pegawai->email || strtolower($pegawai->email->email) !== strtolower($request->email)) {
            return ApiResponse::error('Email tidak sesuai dengan data terdaftar', 'validation_error', null, 422);
        }

        // Generate random password
        $newPassword = Str::random(8);

        try {
            // Update password
            DB::table('user')
                ->where('id_user', DB::raw("AES_ENCRYPT('{$request->username}', '" . env('MYSQL_AES_KEY_IDUSER') . "')"))
                ->update([
                    'password' => DB::raw("AES_ENCRYPT('{$newPassword}', '" . env('MYSQL_AES_KEY_PASSWORD') . "')")
                ]);

            // Send email
            $this->sendResetEmail($pegawai->nama, $request->email, $newPassword);

            return ApiResponse::success('Link reset password telah dikirim ke email Anda');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal mengirim email: ' . $e->getMessage(), 'server_error', null, 500);
        }
    }

    /**
     * Change Password (Reset Flow)
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'old_password' => 'required',
            'new_password' => 'required|min:4|confirmed',
            'captcha_id' => 'required',
            'captcha_code' => 'required'
        ]);

        // Validate Captcha
        $cachedCode = Cache::get('captcha_' . $request->captcha_id);
        if (!$cachedCode || strtoupper($request->captcha_code) !== $cachedCode) {
            return ApiResponse::error('Captcha tidak valid', 'validation_error', null, 422);
        }
        Cache::forget('captcha_' . $request->captcha_id);

        // Validate Username & Old Password
        $user = User::where('id_user', DB::raw("AES_ENCRYPT('{$request->username}', '" . env('MYSQL_AES_KEY_IDUSER') . "')"))
            ->where('password', DB::raw("AES_ENCRYPT('{$request->old_password}', '" . env('MYSQL_AES_KEY_PASSWORD') . "')"))
            ->first();

        if (!$user) {
            return ApiResponse::error('Username atau password lama tidak valid', 'unauthorized', null, 401);
        }

        try {
            // Update to New Password
            DB::table('user')
                ->where('id_user', DB::raw("AES_ENCRYPT('{$request->username}', '" . env('MYSQL_AES_KEY_IDUSER') . "')"))
                ->update([
                    'password' => DB::raw("AES_ENCRYPT('{$request->new_password}', '" . env('MYSQL_AES_KEY_PASSWORD') . "')")
                ]);

            return ApiResponse::success('Password berhasil diperbarui');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memperbarui password: ' . $e->getMessage(), 'server_error', null, 500);
        }
    }

    private function sendResetEmail($name, $to, $password)
    {
        $smtp = DB::table('rsia_email_smtp')->first();
        if (!$smtp) {
            throw new \Exception('Konfigurasi SMTP tidak ditemukan');
        }

        // Configure Mailer on the fly
        config([
            'mail.mailers.smtp.host' => 'smtp.gmail.com', // or search for actual host if possible
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.encryption' => 'tls',
            'mail.mailers.smtp.username' => $smtp->email,
            'mail.mailers.smtp.password' => $smtp->password,
            'mail.from.address' => $smtp->email,
            'mail.from.name' => 'IT RSIA Aisyiyah Pekajangan',
        ]);

        $content = "Halo {$name}, password Anda telah direset menjadi <b>{$password}</b> <br>";
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000/rsiap-v2');
        $content .= "Silahkan melakukan perubahan password pada link berikut : " . rtrim($frontendUrl, '/') . "/change-password";

        Mail::send([], [], function ($message) use ($to, $content) {
            $message->to($to)
                ->subject('Reset Password')
                ->setBody($content, 'text/html');
        });
    }
}
