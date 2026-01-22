<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:aes', 'claim:role,pegawai|dokter'])->group(function () {
    // Dashboard Statistics
    Route::get('dashboard/stats', [\App\Http\Controllers\v2\DashboardController::class, 'getStats'])->name('dashboard.stats');
    Route::get('dashboard/visits', [\App\Http\Controllers\v2\DashboardController::class, 'getVisitStats'])->name('dashboard.visits');
    
    // Code Blue Schedule
    Route::get('dashboard/codeblue', [\App\Http\Controllers\v2\DashboardController::class, 'getCodeBlueSchedule'])->name('dashboard.codeblue');
    Route::get('codeblue/schedule/{date}', [\App\Http\Controllers\v2\DashboardController::class, 'getCodeBlueScheduleByDate'])->name('codeblue.schedule.bydate');
    Route::post('codeblue/schedule', [\App\Http\Controllers\v2\DashboardController::class, 'saveCodeBlueSchedule'])->name('codeblue.schedule.save');
    Route::delete('codeblue/schedule/{date}', [\App\Http\Controllers\v2\DashboardController::class, 'deleteCodeBlueSchedule'])->name('codeblue.schedule.delete');
});
