<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\DataBarangController;

Route::prefix('farmasi')->middleware('auth:aes')->group(function () {
    Route::get('/databarang', [\App\Http\Controllers\v2\DataBarangController::class, 'index']);
    Route::get('/databarang/attributes', [\App\Http\Controllers\v2\DataBarangController::class, 'attributes']);
    Route::get('/databarang/next-code', [\App\Http\Controllers\v2\DataBarangController::class, 'nextCode']);
    Route::post('/databarang', [\App\Http\Controllers\v2\DataBarangController::class, 'store']);
    Route::put('/databarang/{kode_brng}', [\App\Http\Controllers\v2\DataBarangController::class, 'update']);
    Route::delete('/databarang/{kode_brng}', [\App\Http\Controllers\v2\DataBarangController::class, 'destroy']);
    Route::post('/databarang/{kode_brng}/restore', [\App\Http\Controllers\v2\DataBarangController::class, 'restore']);
    Route::get('/databarang/export', [\App\Http\Controllers\v2\DataBarangController::class, 'export']);
    
    // Stok Opname
    Route::get('/stok-opname', [\App\Http\Controllers\v2\StokOpnameController::class, 'index']);
    Route::delete('/stok-opname', [\App\Http\Controllers\v2\StokOpnameController::class, 'destroy']);
    Route::post('/stok-opname', [\App\Http\Controllers\v2\StokOpnameController::class, 'store']);
    Route::post('/stok-opname/bulk', [\App\Http\Controllers\v2\StokOpnameController::class, 'storeBulk']);
    Route::get('/stok-opname/bangsal', [\App\Http\Controllers\v2\StokOpnameController::class, 'bangsal']);
    Route::get('/stok-opname/items', [\App\Http\Controllers\v2\StokOpnameController::class, 'getItems']);
    Route::get('/riwayat-obat', [App\Http\Controllers\v2\RiwayatObatController::class, 'index']);
    Route::get('/riwayat-obat/summary', [App\Http\Controllers\v2\RiwayatObatController::class, 'summary']);
    Route::get('/riwayat-obat/export', [App\Http\Controllers\v2\RiwayatObatController::class, 'export']);
    Route::get('/riwayat-obat/statuses', [App\Http\Controllers\v2\RiwayatObatController::class, 'statuses']);
    Route::get('/riwayat-obat/last-stock', [App\Http\Controllers\v2\RiwayatObatController::class, 'lastStock']);
    Route::get('/riwayat-obat/export-last-stock', [App\Http\Controllers\v2\RiwayatObatController::class, 'exportLastStock']);
});
