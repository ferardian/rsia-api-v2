<?php

use Orion\Facades\Orion;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function ($router) {
    // Custom routes for composite key operations (must be before Orion resource)
    Route::put('jadwal/{resourceKey}', [\App\Http\Controllers\Orion\JadwalDokterController::class, 'customUpdate'])
        ->where('resourceKey', '.*');
    Route::delete('jadwal/{resourceKey}', [\App\Http\Controllers\Orion\JadwalDokterController::class, 'customDestroy'])
        ->where('resourceKey', '.*');
    
    // Orion resource routes
    Orion::resource('jadwal', \App\Http\Controllers\Orion\JadwalDokterController::class)
        ->only(['search', 'index', 'store']);
});
