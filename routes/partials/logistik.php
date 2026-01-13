<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\KodeSatuanController;
use App\Http\Controllers\v2\IpsrsJenisBarangController;

Route::prefix('logistik')->group(function () {
    // Satuan
    Route::get('satuan', [KodeSatuanController::class, 'index']);
    Route::post('satuan', [KodeSatuanController::class, 'store']);
    Route::put('satuan/{id}', [KodeSatuanController::class, 'update']);
    Route::delete('satuan/{id}', [KodeSatuanController::class, 'destroy']);

    Route::get('jenis-barang/next-code', [IpsrsJenisBarangController::class, 'getGeneratedCode']);
    Route::get('jenis-barang', [IpsrsJenisBarangController::class, 'index']);
    Route::post('jenis-barang', [IpsrsJenisBarangController::class, 'store']);
    Route::put('jenis-barang/{id}', [IpsrsJenisBarangController::class, 'update']);
    Route::delete('jenis-barang/{id}', [IpsrsJenisBarangController::class, 'destroy']);

    // Suplier
    Route::get('supplier/next-code', [\App\Http\Controllers\v2\IpsrsSuplierController::class, 'getGeneratedCode']);
    Route::get('supplier', [\App\Http\Controllers\v2\IpsrsSuplierController::class, 'index']);
    Route::post('supplier', [\App\Http\Controllers\v2\IpsrsSuplierController::class, 'store']);
    Route::put('supplier/{id}', [\App\Http\Controllers\v2\IpsrsSuplierController::class, 'update']);
    Route::delete('supplier/{id}', [\App\Http\Controllers\v2\IpsrsSuplierController::class, 'destroy']);

    // Barang
    Route::get('barang/next-code', [\App\Http\Controllers\v2\IpsrsBarangController::class, 'getGeneratedCode']);
    Route::get('barang', [\App\Http\Controllers\v2\IpsrsBarangController::class, 'index']);
    Route::post('barang', [\App\Http\Controllers\v2\IpsrsBarangController::class, 'store']);
    Route::put('barang/{id}', [\App\Http\Controllers\v2\IpsrsBarangController::class, 'update']);
    Route::delete('barang/{id}', [\App\Http\Controllers\v2\IpsrsBarangController::class, 'destroy']);
});
