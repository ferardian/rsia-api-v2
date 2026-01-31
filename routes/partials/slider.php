<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\RsiaSliderController;

Route::get('slider', [RsiaSliderController::class, 'index']);

Route::prefix('slider')->middleware(['claim:role,pegawai'])->group(function () {
    Route::get('all', [RsiaSliderController::class, 'get']);
    Route::post('store', [RsiaSliderController::class, 'store']);
    Route::post('update/{id}', [RsiaSliderController::class, 'update']);
    Route::delete('delete/{id}', [RsiaSliderController::class, 'destroy']);
    Route::post('status/{id}', [RsiaSliderController::class, 'updateStatus']);
});
