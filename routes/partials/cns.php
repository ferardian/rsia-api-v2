<?php

use Illuminate\Support\Facades\Route;

Route::prefix('cns')->middleware(['auth:aes', 'claim:role,pegawai|dokter'])->group(function () {
    // Dokter Off
    Route::get('/dokter-off', [\App\Http\Controllers\v2\CnsDokterOffController::class, 'index']);
    Route::get('/dokter-off/jadwal-pengganti', [\App\Http\Controllers\v2\CnsDokterOffController::class, 'jadwalPengganti']);
    Route::post('/dokter-off/kirim-notifikasi', [\App\Http\Controllers\v2\CnsDokterOffController::class, 'kirimNotifikasi']);

    // Jam Poli (reuses data endpoints from dokter-off, different notification)
    Route::get('/jam-poli', [\App\Http\Controllers\v2\CnsDokterOffController::class, 'index']);
    Route::post('/jam-poli/kirim-notifikasi', [\App\Http\Controllers\v2\CnsDokterOffController::class, 'kirimNotifikasiJamPoli']);

    // Konfirmasi Hadir
    Route::get('/konfirmasi-hadir', [\App\Http\Controllers\v2\CnsDokterOffController::class, 'index']);
    Route::post('/konfirmasi-hadir/kirim-notifikasi', [\App\Http\Controllers\v2\CnsDokterOffController::class, 'kirimNotifikasiKonfirmasiHadir']);

    // Kontrol
    Route::get('/kontrol', [\App\Http\Controllers\v2\CnsKontrolController::class, 'index']);
    Route::post('/kontrol/kirim-notifikasi', [\App\Http\Controllers\v2\CnsKontrolController::class, 'kirimNotifikasi']);

    // Shared helper endpoints
    Route::get('/dokter-off/dokter', [\App\Http\Controllers\v2\CnsDokterOffController::class, 'getDokter']);
    Route::get('/dokter-off/poliklinik', [\App\Http\Controllers\v2\CnsDokterOffController::class, 'getPoliklinik']);
});

