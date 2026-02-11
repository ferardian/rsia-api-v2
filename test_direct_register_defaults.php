<?php

require 'vendor/autoload.php';

// Load app normally
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Force Debug and Local Env for Test
config(['app.debug' => true]);
config(['app.env' => 'local']);

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\v2\PasienRegistrationController;
use Illuminate\Http\Request;

echo "🧪 [DIRECT REGISTRATION TEST - DEFAULT ADDRESS] Starting verification...\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// 1. Prepare Dummy Data (WITHOUT Address IDs to test defaults)
$testNik = "99" . time() . random_int(1000, 9999);
$testPhone = '08' . time() . random_int(10, 99); // Unique Phone to bypass RateLimit
$request = new Request(); // Dummy request helper

// --- SECURITY FLOW START ---
$controller = new PasienRegistrationController();

echo "🔐 [SECURITY CHECK] Starting OTP Flow...\n";

// 0. Reset Rate Limit for Test
echo "   -> Resetting Rate Limit for test...\n";
// In CLI, request->ip() might return null or 127.0.0.1.
// Debug showed: send_otp::6281234567890
Illuminate\Support\Facades\RateLimiter::clear('send_otp::' . $testPhone); 
Illuminate\Support\Facades\RateLimiter::clear('send_otp:127.0.0.1:' . $testPhone);

// Clear Register Rate Limit too (just in case)
Illuminate\Support\Facades\RateLimiter::clear('register_pasien::'); 
Illuminate\Support\Facades\RateLimiter::clear('register_pasien:127.0.0.1');

// A. Send OTP
echo "   -> Sending OTP to $testPhone...\n";
$reqOtp = new Request(['no_telp' => $testPhone]);
$resOtp = $controller->sendOtp($reqOtp);
$dataOtp = $resOtp->getData();

if (!isset($dataOtp->success) || !$dataOtp->success) {
    die("❌ Failed to send OTP: " . json_encode($dataOtp) . "\n");
}

$otpCode = $dataOtp->data->otp ?? null; // Only works if APP_DEBUG=true or we expose it
echo "   ✅ OTP Sent! Code (Debug): $otpCode\n";

if (!$otpCode) {
    // If not in debug, we can't test automatically unless we peek into Cache
    // But for this test env, we assume we get it.
    // Let's manually peek Cache if needed, but the controller change I made exposes it in debug.
    die("❌ OTP Code not received. Ensure config('app.debug') is true for testing.\n");
}

// B. Verify OTP
echo "   -> Verifying OTP...\n";
$reqVerify = new Request(['no_telp' => $testPhone, 'otp' => $otpCode]);
$resVerify = $controller->verifyOtp($reqVerify);
$dataVerify = $resVerify->getData();

if (!isset($dataVerify->success) || !$dataVerify->success) {
    die("❌ Failed to verify OTP: " . json_encode($dataVerify) . "\n");
}

$regToken = $dataVerify->data->token;
echo "   ✅ OTP Verified! Token: " . substr($regToken, 0, 10) . "...\n";

// --- SECURITY FLOW END ---

$testData = [
    'reg_token'    => $regToken, // MANDATORY NOW
    'nik'          => $testNik,
    'nm_pasien'    => "TEST PASIEN SECURE " . date('His'),
    'jk'           => 'L',
    'tmp_lahir'    => 'PEKALONGAN',
    'tgl_lahir'    => '1990-01-01',
    'nm_ibu'       => 'IBU TEST SECURE',
    'alamat'       => 'ALAMAT TEST SECURE',
    'no_telp'      => $testPhone,
    'email'        => 'test_secure@example.com',
    'namakeluarga' => 'PJ TEST SECURE',
    'keluarga'     => 'AYAH',
];

echo "📝 Test NIK: $testNik\n";
echo "📝 Test Name: {$testData['nm_pasien']}\n";

// 2. Fetch Current Next RM (Last Used)
$currentLastRm = DB::table('set_no_rkm_medis')->value('no_rkm_medis');
echo "🔢 Current Last RM in set_no_rkm_medis: $currentLastRm\n";

// 3. Execute Register
$request = new Request($testData);
// Controller already instantiated

try {
    $response = $controller->register($request);
    $responseData = $response->getData(); // This returns an object

    if ($response->getStatusCode() == 200 && isset($responseData->success) && $responseData->success) {
        echo "✅ Registration SUCCESS!\n";
        echo "📄 Raw Response: " . json_encode($responseData) . "\n";
        
        $noRm = 'UNKNOWN';
        if (is_object($responseData) && isset($responseData->message) && is_object($responseData->message) && isset($responseData->message->no_rkm_medis)) {
             $noRm = $responseData->message->no_rkm_medis;
        }
        
        echo "🆔 Assigned RM: $noRm\n";

        // 4. Verify in Database
        $pasien = DB::table('pasien')->where('no_ktp', $testNik)->first();
        if ($pasien) {
            echo "✅ Record FOUND in 'pasien' table.\n";
            echo "👤 Name in DB: {$pasien->nm_pasien}\n";
            echo "📍 Kelurahan ID in DB: {$pasien->kd_kel} (Expected: 1)\n";
            echo "📍 Kecamatan ID in DB: {$pasien->kd_kec} (Expected: 1)\n";
            echo "📍 Kabupaten ID in DB: {$pasien->kd_kab} (Expected: 1)\n";
            echo "📍 Propinsi ID in DB: {$pasien->kd_prop} (Expected: 1)\n";
            
            if ($pasien->kd_kel == 1 && $pasien->kd_kec == 1 && $pasien->kd_kab == 1 && $pasien->kd_prop == 1) {
                echo "✅ Address Defaults Applied CORRECTLY.\n";
            } else {
                echo "❌ Address Defaults NOT Applied Correctly.\n";
            }
        } else {
            echo "❌ Record NOT FOUND in 'pasien' table.\n";
        }

        // 5. Verify RM Counter Update
        $finalLastRm = DB::table('set_no_rkm_medis')->value('no_rkm_medis');
        echo "🔢 Final Last RM in set_no_rkm_medis: $finalLastRm\n";
        
        if ((int)$finalLastRm == (int)$noRm) {
            echo "✅ RM Counter INCREMENTED correctly (Matches assigned RM).\n";
        } else {
            echo "❌ RM Counter NOT incremented correctly. Expected $noRm, got $finalLastRm\n";
        }

        // Cleanup test data
        // DB::table('pasien')->where('no_ktp', $testNik)->delete();
        // echo "🧹 Test data cleaned up.\n";
        echo "⚠️ Test data NOT cleaned up (Check DB for NIK: $testNik)\n";

    } else {
        echo "❌ Registration FAILED!\n";
        $msg = is_string($responseData->message) ? $responseData->message : json_encode($responseData->message);
        echo "🛑 Message: $msg\n";
        if (isset($responseData->error)) {
            echo "❗ Error Type: {$responseData->error}\n";
        }
    }
} catch (\Exception $e) {
    echo "🚨 EXCEPTION: " . $e->getMessage() . "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎉 Test Completed.\n";
