<?php

use Illuminate\Support\Facades\Route;

// Add system route without middleware for now (temporary fix)
Route::prefix('mapping-lab')->group(function () {
    Route::get('/system', [\App\Http\Controllers\v2\RsiaMappingLabController::class, 'getBySystem'])->name('mapping-lab.system');
});

Route::middleware(['claim:role,pegawai|dokter'])->prefix('mapping-lab')->group(function () {
    // ==================== RSIA MAPPING LAB CRUD
    Route::apiResource('/', \App\Http\Controllers\v2\RsiaMappingLabController::class)->parameters(['' => 'kd_jenis_prw']);

    // ==================== ADDITIONAL ENDPOINTS
    Route::get('/jenis-perawatan/{kdJenisPrw}', [\App\Http\Controllers\v2\RsiaMappingLabController::class, 'getByJenisPerawatan'])->name('mapping-lab.jenis-perawatan');
    Route::post('/bulk', [\App\Http\Controllers\v2\RsiaMappingLabController::class, 'bulkStore'])->name('mapping-lab.bulk');
});