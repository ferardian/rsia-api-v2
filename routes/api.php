<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\RsiaOtpController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::get('/', function (Request $request) {
    try {
        $c = \Illuminate\Support\Facades\DB::connection()->getPdo();
        return response()->json([
            'message' => 'Database connection success!',
            'connection' => $c->getAttribute(PDO::ATTR_CONNECTION_STATUS)
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Database connection failed!',
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::get('/credit', function (Request $request) {
    return response()->json([
        'developer'   => 'M Faisal Halim',
        'email'       => 'ffaisalhalim@gmail.com',
        'github'      => 'https://github.com/halimkun',
        'repository'  => 'https://github.com/halimkun/rsia-api-v2.git',
        'license'     => 'MIT License',
        'license_url' => 'https://github.com/halimkun/rsia-api-v2/blob/master/LICENSE',
        'version'     => 'Laravel ' . app()->version(),
    ], 200);
});

Route::middleware(['claim:role,pegawai|dokter|pasien'])->prefix('notification')->group(function () {
    Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::put('/{id}/read', [\App\Http\Controllers\NotificationController::class, 'read']);
    Route::patch('/{id}/read', [\App\Http\Controllers\NotificationController::class, 'read']);
    Route::delete('/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy']);

    Route::middleware(['user-aes'])->group(function () {
        Route::post('test', function (Request $request) {
            \App\Jobs\JadwalPraktikDokter::dispatch('perubahan_jadwal_dokter', ['067989'], collect($request->all()));
        });
        Route::post('send', [\App\Http\Controllers\v2\NotificationController::class, 'send']);
        Route::post('with-template', [\App\Http\Controllers\v2\NotificationController::class, 'withTemplate']);
    });
});





// ========== OTP ==========
Route::prefix('otp')->middleware(['detail-user', 'claim:role,pegawai|dokter|pasien'])->group(function () {
    Route::post('create', [RsiaOtpController::class, 'createOtp']);
    Route::post('verify', [RsiaOtpController::class, 'verifyOtp']);
    Route::post('resend', [RsiaOtpController::class, 'resendOtp']);
    Route::middleware(['claim:dep,IT'])->post('invalidate-expired', [RsiaOtpController::class, 'invalidateExpiredOtps']);
});
// ======== END OTP ========

// Wilayah (Address) Data
Route::get('/wilayah/propinsi', [\App\Http\Controllers\v2\WilayahController::class, 'getPropinsi']);
Route::get('/wilayah/kabupaten', [\App\Http\Controllers\v2\WilayahController::class, 'getKabupaten']);
Route::get('/wilayah/kecamatan', [\App\Http\Controllers\v2\WilayahController::class, 'getKecamatan']);
Route::get('/wilayah/kelurahan', [\App\Http\Controllers\v2\WilayahController::class, 'getKelurahan']);

// Logistik Master Data routes moved to routes/partials/logistik.php


// Temporary test route for satu_sehat_mapping_lab
Route::get('/test-mapping-templates', function () {
    try {
        $templateMappings = \DB::table('satu_sehat_mapping_lab')->get();

        return response()->json([
            'success' => true,
            'data' => $templateMappings
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});



$files = scandir(__DIR__ . '/partials');
foreach ($files as $file) {
    // if file is not a directory
    if (!is_dir(__DIR__ . '/partials/' . $file)) {
        // require_once the file
        require_once __DIR__ . '/partials/' . $file;
    }
}

    // Rawat Jalan
    require __DIR__ . '/partials/rawat-jalan.php';

    // Rawat Inap
    require __DIR__ . '/partials/rawat-inap.php';

require_once __DIR__ . '/eklaim-api.php';
require_once __DIR__ . '/oauth-api.php';
require_once __DIR__ . '/bpjs-api.php';