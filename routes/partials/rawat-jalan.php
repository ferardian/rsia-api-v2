<?php

use Illuminate\Support\Facades\Route;

Route::prefix('rawat-jalan')->middleware(['claim:role,pegawai|dokter|kasir'])->group(function () {
    Route::get('/', [\App\Http\Controllers\v2\RawatJalanController::class, 'index']);
    Route::get('/poli', [\App\Http\Controllers\v2\RawatJalanController::class, 'poli']);
    Route::get('/dokter', [\App\Http\Controllers\v2\RawatJalanController::class, 'dokter']);
    Route::get('/billing', [\App\Http\Controllers\v2\RawatJalanController::class, 'billing']);
    Route::get('/penunjang', [\App\Http\Controllers\v2\RawatJalanController::class, 'penunjang']);
});
