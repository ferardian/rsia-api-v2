<?php

use Illuminate\Support\Facades\Route;

Route::prefix('file-manager')->group(function () {
    Route::get('/', [\App\Http\Controllers\v2\FileManagerController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\v2\FileManagerController::class, 'store']);
    Route::put('/{id}', [\App\Http\Controllers\v2\FileManagerController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\v2\FileManagerController::class, 'destroy']);
    Route::get('/download/{id}', [\App\Http\Controllers\v2\FileManagerController::class, 'download']);
});
