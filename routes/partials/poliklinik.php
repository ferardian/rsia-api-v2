<?php

use Orion\Facades\Orion;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function ($router) {
    Route::get('antrian-poli/summary', [\App\Http\Controllers\v2\AntrianPoliController::class, 'antrianSummary']);
    Orion::resource('poliklinik', \App\Http\Controllers\Orion\PoliklinikController::class)->only(['search', 'show', 'index'])
        ->parameters(['poliklinik' => 'kd_poli']);
});
