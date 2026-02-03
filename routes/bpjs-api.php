<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BPJS API Routes
|--------------------------------------------------------------------------
|
| Di sini Anda dapat mendaftarkan rute API khusus untuk integrasi BPJS.
| Rute-rute ini dimuat oleh RouteServiceProvider dalam grup yang
| diberi middleware "api".
|
*/

Route::group(['middleware' => ['auth:aes']], function () {
    // Route untuk cek setting BPJS
    Route::get('/bpjs/setting-info', [\App\Http\Controllers\v2\BpjsController::class, 'getSettingInfo']);

    // Route untuk insert rekam medis ke BPJS
    Route::post('/bpjs/rekammedis/insert', [\App\Http\Controllers\v2\BpjsController::class, 'insertMedicalRecord']);

    // Route untuk mendapatkan data ERM yang tersimpan
    Route::get('/bpjs/erm/{noSep}', [\App\Http\Controllers\v2\BpjsController::class, 'getErmData']);

    // Route untuk mendapatkan bundle ERM asli (sebelum enkripsi)
    Route::get('/bpjs/erm/{noSep}/bundle', [\App\Http\Controllers\v2\BpjsController::class, 'getErmBundle']);

    // Route untuk mendapatkan response BPJS
    Route::get('/bpjs/erm/{noSep}/response', [\App\Http\Controllers\v2\BpjsController::class, 'getBpjsResponse']);

    // BPJS Antrol (Antrian Online)
    Route::get('/bpjs/antrol/pendaftaran/tanggal/{tanggal}', [\App\Http\Controllers\v2\BpjsAntrolController::class, 'getPendaftaranByTanggal']);
    Route::get('/bpjs/antrol/pendaftaran/range/{tglAwal}/{tglAkhir}', [\App\Http\Controllers\v2\BpjsAntrolController::class, 'getPendaftaranByRange']);
    Route::get('/bpjs/antrol/dashboard', [\App\Http\Controllers\v2\BpjsAntrolController::class, 'getDashboardByTanggal']);
    Route::post('/bpjs/antrol/antrean/getlisttask', [\App\Http\Controllers\v2\BpjsAntrolController::class, 'getListTask']);
    Route::post('/bpjs/antrol/antrean/sync', [\App\Http\Controllers\v2\BpjsAntrolController::class, 'syncTask']);
    Route::post('/bpjs/antrol/antrean/local-data', [\App\Http\Controllers\v2\BpjsAntrolController::class, 'getLocalData']);
    Route::post('/bpjs/antrol/antrean/update-local', [\App\Http\Controllers\v2\BpjsAntrolController::class, 'updateLocalTask']);
    Route::post('/bpjs/antrol/antrean/sync-queue', [\App\Http\Controllers\v2\BpjsAntrolController::class, 'syncTaskQueue']);
    Route::get('/bpjs/antrol/sep/count/{tanggal}', [\App\Http\Controllers\v2\BpjsAntrolController::class, 'getSepCount']);
    Route::get('/bpjs/antrol/sep/range/{tglAwal}/{tglAkhir}', [\App\Http\Controllers\v2\BpjsAntrolController::class, 'getSepCountByRange']);
});