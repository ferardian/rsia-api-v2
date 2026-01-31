<?php
use Illuminate\Support\Facades\Route;

Route::middleware(['claim:role,pegawai|dokter|perawat|kasir'])->prefix('operasi')->group(function () {
    Route::get('booking', [\App\Http\Controllers\v2\OperasiController::class, 'index']);
    Route::post('booking', [\App\Http\Controllers\v2\OperasiController::class, 'store']);
    Route::put('booking/{no_rawat}/{tanggal}', [\App\Http\Controllers\v2\OperasiController::class, 'update']);
    Route::delete('booking', [\App\Http\Controllers\v2\OperasiController::class, 'destroy']);
    Route::get('paket', [\App\Http\Controllers\v2\OperasiController::class, 'paket']);
    
    // Laporan Operasi & Master Data
    Route::get('laporan/list', [\App\Http\Controllers\v2\OperasiController::class, 'indexLaporan']);
    Route::get('laporan', [\App\Http\Controllers\v2\OperasiController::class, 'getLaporan']);
    Route::post('laporan', [\App\Http\Controllers\v2\OperasiController::class, 'storeLaporan']);
    Route::delete('laporan', [\App\Http\Controllers\v2\OperasiController::class, 'destroyLaporan']);
    Route::get('dokter', [\App\Http\Controllers\v2\OperasiController::class, 'getDokter']);
    Route::get('pegawai', [\App\Http\Controllers\v2\OperasiController::class, 'getPegawai']);
    Route::get('penjab', [\App\Http\Controllers\v2\OperasiController::class, 'getPenjab']);
});
