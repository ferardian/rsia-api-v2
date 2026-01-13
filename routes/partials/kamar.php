<?php

use Orion\Facades\Orion;
use Illuminate\Support\Facades\Route;

// Public routes for master data kamar
Route::prefix('kamar/master')->group(function () {
    Route::get('/', [\App\Http\Controllers\v2\KamarController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\v2\KamarController::class, 'store']);
    Route::put('/{kd_kamar}', [\App\Http\Controllers\v2\KamarController::class, 'update']);
    Route::delete('/{kd_kamar}', [\App\Http\Controllers\v2\KamarController::class, 'destroy']);
    Route::get('/bangsal', [\App\Http\Controllers\v2\KamarController::class, 'getBangsal']);
    Route::get('/indent', [\App\Http\Controllers\v2\KamarController::class, 'getIndent']);
    Route::put('/indent/{kd_indent}', [\App\Http\Controllers\v2\KamarController::class, 'updateIndent']);
    Route::delete('/indent/{kd_indent}', [\App\Http\Controllers\v2\KamarController::class, 'deleteIndent']);
});

Route::middleware(['claim:role,pegawai|pasien|dokter'])->group(function ($router) {
    
    
    // kamar inap data
    Route::prefix('kamar')->group(function($router) {
        Orion::resource('inap', \App\Http\Controllers\Orion\KamarInapController::class)->only(['search', 'index'])
            ->parameters([ 'inap' => 'base64-no_rawat' ]);
        Route::apiResource('inap', \App\Http\Controllers\v2\KamarInapController::class)->only(['show'])
            ->parameters([ 'inap' => 'base64-no_rawat' ]);
    });

    // Bed availability routes
    Route::prefix('bed-availability')->group(function($router) {
        Route::get('/', [\App\Http\Controllers\v2\BedAvailabilityController::class, 'index']);
        Route::get('/summary', [\App\Http\Controllers\v2\BedAvailabilityController::class, 'summary']);
        Route::get('/wards', [\App\Http\Controllers\v2\BedAvailabilityController::class, 'getWards']);
        Route::get('/classes', [\App\Http\Controllers\v2\BedAvailabilityController::class, 'getClasses']);
        Route::get('/ward/{kd_bangsal}', [\App\Http\Controllers\v2\BedAvailabilityController::class, 'getByWard']);
    });
});
