<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\MasterIndikatorMutuController;
use App\Http\Controllers\v2\MonitoringIndikatorMutuController;

Route::prefix('indikator-mutu')->group(function () {
    // Monitoring
    Route::get('/monitoring', [MonitoringIndikatorMutuController::class, 'index']);
    Route::get('/monitoring/units', [MonitoringIndikatorMutuController::class, 'getUnits']);
    Route::get('/laporan', [MonitoringIndikatorMutuController::class, 'getLaporan']);
    Route::get('/realisasi', [MonitoringIndikatorMutuController::class, 'getRealisasi']);
    Route::post('/realisasi', [MonitoringIndikatorMutuController::class, 'storeRealisasi']);
    Route::post('/realisasi/bulk', [MonitoringIndikatorMutuController::class, 'storeRealisasiBulk']);
    
    // Analisa
    Route::get('/analisa', [MonitoringIndikatorMutuController::class, 'getAnalisa']);
    Route::post('/analisa', [MonitoringIndikatorMutuController::class, 'storeAnalisa']);
    Route::put('/analisa/{id}', [MonitoringIndikatorMutuController::class, 'updateAnalisa']);
    Route::delete('/analisa/{id}', [MonitoringIndikatorMutuController::class, 'deleteAnalisa']);

    // Master Utama
    Route::get('/master/utama', [MasterIndikatorMutuController::class, 'indexUtama']);
    Route::post('/master/utama', [MasterIndikatorMutuController::class, 'storeUtama']);
    Route::put('/master/utama/{id}', [MasterIndikatorMutuController::class, 'updateUtama']);
    Route::delete('/master/utama/{id}', [MasterIndikatorMutuController::class, 'destroyUtama']);

    // Master Ruang
    Route::get('/master/ruang', [MasterIndikatorMutuController::class, 'indexRuang']);
    Route::post('/master/ruang', [MasterIndikatorMutuController::class, 'storeRuang']);
    Route::put('/master/ruang/{id}', [MasterIndikatorMutuController::class, 'updateRuang']);
    Route::delete('/master/ruang/{id}', [MasterIndikatorMutuController::class, 'destroyRuang']);
});
