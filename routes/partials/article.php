<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\RsiaArticleController;

Route::get('/article', [RsiaArticleController::class, 'index']);

Route::middleware('claim:role,pegawai')->group(function () {
    Route::get('/article/get', [RsiaArticleController::class, 'get']);
    Route::post('/article/store', [RsiaArticleController::class, 'store']);
    Route::post('/article/update/{id}', [RsiaArticleController::class, 'update']);
    Route::post('/article/delete/{id}', [RsiaArticleController::class, 'destroy']);
    Route::post('/article/update/status/{id}', [RsiaArticleController::class, 'updateStatus']);
});
