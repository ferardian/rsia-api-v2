<?php

use App\Http\Controllers\v2\Laporan\DiagnosaPenyakitController;
use Illuminate\Support\Facades\Route;

Route::prefix('laporan/penyakit')->group(function () {
    Route::get('top10', [DiagnosaPenyakitController::class, 'getTop10']);
    Route::get('summary', [DiagnosaPenyakitController::class, 'getSummary']);
    Route::get('death-details', [DiagnosaPenyakitController::class, 'getDeathDetails']);
    Route::get('deadliest', [DiagnosaPenyakitController::class, 'getDeadliestDiseases']);
});
