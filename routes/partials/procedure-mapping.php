<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['claim:role,pegawai|dokter'])->prefix('procedure-mapping')->group(function () {
    // ==================== PROCEDURE MAPPING ENDPOINTS

    // Basic CRUD operations
    Route::get('/', [\App\Http\Controllers\v2\RsiaMappingProcedureController::class, 'index'])->name('procedure-mapping.index');
    Route::post('/', [\App\Http\Controllers\v2\RsiaMappingProcedureController::class, 'store'])->name('procedure-mapping.store');
    Route::get('/{kd_jenis_prw}', [\App\Http\Controllers\v2\RsiaMappingProcedureController::class, 'show'])->name('procedure-mapping.show');
    Route::put('/{kd_jenis_prw}', [\App\Http\Controllers\v2\RsiaMappingProcedureController::class, 'update'])->name('procedure-mapping.update');
    Route::delete('/{kd_jenis_prw}', [\App\Http\Controllers\v2\RsiaMappingProcedureController::class, 'destroy'])->name('procedure-mapping.destroy');

    // Additional endpoints
    Route::get('/unmapped/procedures', [\App\Http\Controllers\v2\RsiaMappingProcedureController::class, 'unmapped'])->name('procedure-mapping.unmapped');
    Route::get('/by-snomed-code', [\App\Http\Controllers\v2\RsiaMappingProcedureController::class, 'getBySnomedCode'])->name('procedure-mapping.by-snomed-code');
    Route::post('/bulk-update', [\App\Http\Controllers\v2\RsiaMappingProcedureController::class, 'bulkUpdate'])->name('procedure-mapping.bulk-update');
    Route::get('/stats', [\App\Http\Controllers\v2\RsiaMappingProcedureController::class, 'stats'])->name('procedure-mapping.stats');
});