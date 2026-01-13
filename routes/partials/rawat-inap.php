<?php

use Illuminate\Support\Facades\Route;

Route::prefix('rawat-inap')->group(function () {
    Route::get('/', [\App\Http\Controllers\v2\RawatInapController::class, 'index']);
    Route::get('/spesialis', [\App\Http\Controllers\v2\RawatInapController::class, 'spesialis']);
    Route::get('/billing', [\App\Http\Controllers\v2\RawatInapController::class, 'billing']);
    Route::get('/penunjang', [\App\Http\Controllers\v2\RawatInapController::class, 'penunjang']);
    
    // Diet routes - using query params to handle slashes in no_rawat
    Route::get('/diet', [\App\Http\Controllers\v2\PermintaanDietController::class, 'index']);
    Route::get('/diet/show', [\App\Http\Controllers\v2\PermintaanDietController::class, 'show']);
    Route::post('/diet', [\App\Http\Controllers\v2\PermintaanDietController::class, 'store']);
    Route::post('/diet/bulk', [\App\Http\Controllers\v2\PermintaanDietController::class, 'bulkStore']);
    Route::delete('/diet/delete', [\App\Http\Controllers\v2\PermintaanDietController::class, 'destroy']);
    
    // Skrining Gizi routes
    Route::get('/skrining-gizi', [\App\Http\Controllers\v2\SkriningGiziController::class, 'index']);
    Route::get('/skrining-gizi/{no_rawat}', [\App\Http\Controllers\v2\SkriningGiziController::class, 'show']);
    Route::post('/skrining-gizi', [\App\Http\Controllers\v2\SkriningGiziController::class, 'store']);
    Route::delete('/skrining-gizi/{no_rawat}', [\App\Http\Controllers\v2\SkriningGiziController::class, 'destroy']);
});
