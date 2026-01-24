<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\InmutLabController;

Route::middleware(['auth:user-aes', 'detail-user'])->prefix('laboratorium')->group(function () {
    Route::get('/indikator', [InmutLabController::class, 'index']);
    Route::post('/indikator', [InmutLabController::class, 'store']);
});
