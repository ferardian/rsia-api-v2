<?php

use App\Http\Controllers\v2\Laporan\DiagnosaPenyakitController;
use Illuminate\Support\Facades\Route;

Route::prefix('laporan/penyakit')->group(function () {
    Route::get('top10', [DiagnosaPenyakitController::class, 'getTop10']);
    Route::get('summary', [DiagnosaPenyakitController::class, 'getSummary']);
    Route::get('death-details', [DiagnosaPenyakitController::class, 'getDeathDetails']);
    Route::get('deadliest', [DiagnosaPenyakitController::class, 'getDeadliestDiseases']);
});
Route::prefix('laporan/statistik')->group(function () {
    Route::get('ranap/indicators', [\App\Http\Controllers\v2\Laporan\Inap\BedIndicatorController::class, 'getIndicators']);
    Route::get('ranap/indicators/yearly', [\App\Http\Controllers\v2\Laporan\Inap\BedIndicatorController::class, 'getYearlyIndicators']);
    Route::get('ranap/morbiditas', [\App\Http\Controllers\v2\Laporan\RsiaMorbiditasRanapController::class, 'index']);
    Route::get('ralan/morbiditas', [\App\Http\Controllers\v2\Laporan\RsiaMorbiditasRalanController::class, 'index']);
});

Route::get('laporan/rekap-presensi', [\App\Http\Controllers\v2\Laporan\RekapPresensiController::class, 'index']);
Route::get('laporan/rekap-presensi/summary', [\App\Http\Controllers\v2\Laporan\RekapPresensiController::class, 'getSummary']);
