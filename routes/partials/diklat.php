<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v2\DiklatController;

Route::prefix('diklat')->group(function () {
    Route::get('/pegawai/{nik}', [DiklatController::class, 'getDiklatByNik']);
    Route::get('/download/{file}', [DiklatController::class, 'downloadBerkas']);
});
