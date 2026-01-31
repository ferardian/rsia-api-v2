<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\RsiaFacilityController;

// Public routes
Route::get('/facility', [RsiaFacilityController::class, 'index']);

// Protected routes
Route::middleware(['claim:role,pegawai'])->group(function () {
    Route::get('/facility/all', [RsiaFacilityController::class, 'get']);
    Route::post('/facility', [RsiaFacilityController::class, 'store']);
    Route::post('/facility/{id}', [RsiaFacilityController::class, 'update']);
    Route::delete('/facility/{id}', [RsiaFacilityController::class, 'destroy']);
    Route::post('/facility/{id}/status', [RsiaFacilityController::class, 'updateStatus']);
});
