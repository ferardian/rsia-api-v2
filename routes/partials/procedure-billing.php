<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['claim:role,pegawai|dokter'])->prefix('procedure-billing')->group(function () {
    // ==================== PROCEDURE BILLING ENDPOINTS
    Route::get('/', [\App\Http\Controllers\v2\ProcedureBillingController::class, 'index'])->name('procedure-billing.index');
    Route::get('/summary', [\App\Http\Controllers\v2\ProcedureBillingController::class, 'summary'])->name('procedure-billing.summary');
    Route::get('/top-procedures', [\App\Http\Controllers\v2\ProcedureBillingController::class, 'topProcedures'])->name('procedure-billing.top-procedures');
});

// Temporary routes for testing without middleware
Route::prefix('test-procedure-billing')->group(function () {
    Route::get('/', [\App\Http\Controllers\v2\ProcedureBillingController::class, 'index']);
    Route::get('/summary', [\App\Http\Controllers\v2\ProcedureBillingController::class, 'summary']);
    Route::get('/top-procedures', [\App\Http\Controllers\v2\ProcedureBillingController::class, 'topProcedures']);
});