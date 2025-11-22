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
});