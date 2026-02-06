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
});
