<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\Aset\InventarisJenisController;
use App\Http\Controllers\v2\Aset\InventarisKategoriController;
use App\Http\Controllers\v2\Aset\InventarisMerkController;
use App\Http\Controllers\v2\Aset\InventarisProdusenController;
use App\Http\Controllers\v2\Aset\InventarisRuangController;
use App\Http\Controllers\v2\Aset\InventarisSuplierController;

Route::middleware(['auth:aes', 'claim:role,pegawai|IT|admin|direksi'])->prefix('aset')->group(function () {
    Route::apiResource('jenis', InventarisJenisController::class);
    Route::apiResource('kategori', InventarisKategoriController::class);
    Route::apiResource('merk', InventarisMerkController::class);
    Route::apiResource('produsen', InventarisProdusenController::class);
    Route::apiResource('ruang', InventarisRuangController::class);
    Route::apiResource('suplier', InventarisSuplierController::class);

    // Manajemen Inventaris
    Route::apiResource('inventaris-barang', \App\Http\Controllers\v2\Aset\InventarisBarangController::class);
    Route::apiResource('inventaris', \App\Http\Controllers\v2\Aset\InventarisController::class);
    Route::apiResource('sirkulasi', \App\Http\Controllers\v2\Aset\InventarisPeminjamanController::class);
    Route::apiResource('hibah', \App\Http\Controllers\v2\Aset\InventarisHibahController::class);

    // Pemeliharaan
    Route::apiResource('permintaan-perbaikan', \App\Http\Controllers\v2\Aset\PermintaanPerbaikanController::class);
    Route::apiResource('perbaikan-inventaris', \App\Http\Controllers\v2\Aset\PerbaikanInventarisController::class);
    Route::put('pemeliharaan-inventaris/update', [\App\Http\Controllers\v2\Aset\PemeliharaanInventarisController::class, 'update']);
    Route::delete('pemeliharaan-inventaris', [\App\Http\Controllers\v2\Aset\PemeliharaanInventarisController::class, 'destroy']);
    Route::apiResource('pemeliharaan-inventaris', \App\Http\Controllers\v2\Aset\PemeliharaanInventarisController::class)->except(['update', 'destroy']);
    Route::apiResource('pemeliharaan-gedung', \App\Http\Controllers\v2\Aset\PemeliharaanGedungController::class);
});
