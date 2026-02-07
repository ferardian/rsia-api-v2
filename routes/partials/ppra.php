<?php

use Illuminate\Support\Facades\Route;

Route::prefix('ppra')->middleware(['auth:aes'])->group(function () {
    Route::get('tim', [\App\Http\Controllers\v2\RsiaTimPpraController::class, 'index']);
    Route::post('tim', [\App\Http\Controllers\v2\RsiaTimPpraController::class, 'store']);
    Route::put('tim/{id}', [\App\Http\Controllers\v2\RsiaTimPpraController::class, 'update']);
    Route::delete('tim/{id}', [\App\Http\Controllers\v2\RsiaTimPpraController::class, 'destroy']);

    // Mapping Obat
    Route::get('mapping-obat/search-obat', [\App\Http\Controllers\v2\RsiaPpraMappingObatController::class, 'searchObat']);
    Route::apiResource('mapping-obat', \App\Http\Controllers\v2\RsiaPpraMappingObatController::class);

    Route::get('laporan', [\App\Http\Controllers\v2\RsiaPpraReportController::class, 'laporan']);
    Route::post('verifikasi', [\App\Http\Controllers\v2\RsiaPpraVerificationController::class, 'store']);
    Route::post('verifikasi/telaah', [\App\Http\Controllers\v2\RsiaPpraVerificationController::class, 'telaah']);
    Route::post('verifikasi/approve', [\App\Http\Controllers\v2\RsiaPpraVerificationController::class, 'approve']);
    Route::get('verifikasi', [\App\Http\Controllers\v2\RsiaPpraVerificationController::class, 'show']);
});

Route::prefix('ppra')->group(function () {
    Route::post('verifikasi-wa', [\App\Http\Controllers\v2\RsiaPpraVerificationController::class, 'verifyWa']);
});
