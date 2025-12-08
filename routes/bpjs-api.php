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
});