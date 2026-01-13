<?php

use Illuminate\Support\Facades\Route;

Route::prefix('jadwal-dokter')->group(function () {
    Route::get('/', [\App\Http\Controllers\v2\JadwalDokterController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\v2\JadwalDokterController::class, 'store']);
    Route::put('/', [\App\Http\Controllers\v2\JadwalDokterController::class, 'update']);
    Route::delete('/', [\App\Http\Controllers\v2\JadwalDokterController::class, 'destroy']);
    
    // Helper endpoints
    Route::get('/dokter', [\App\Http\Controllers\v2\JadwalDokterController::class, 'getDokter']);
    Route::get('/poliklinik', [\App\Http\Controllers\v2\JadwalDokterController::class, 'getPoliklinik']);
});
