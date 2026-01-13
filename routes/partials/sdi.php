<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\JadwalPegawaiController;
use App\Http\Controllers\v2\PegawaiController;
use App\Http\Controllers\v2\JadwalTambahanController;

Route::middleware(['auth:aes', 'claim:role,pegawai|dokter|IT|admin|direksi'])->prefix('sdi')->group(function () {
    // Jadwal Pegawai Routes
    Route::get('jadwal-pegawai', [JadwalPegawaiController::class, 'index']);
    Route::post('jadwal-pegawai', [JadwalPegawaiController::class, 'store']);
    Route::post('jadwal-pegawai/admin', [JadwalPegawaiController::class, 'storeAdmin']);
    Route::post('jadwal-pegawai/approve', [JadwalPegawaiController::class, 'approve']);
    Route::post('jadwal-pegawai/approve', [JadwalPegawaiController::class, 'approve']);
    Route::get('shifts', [JadwalPegawaiController::class, 'getShifts']);

    // Jadwal Tambahan Routes
    Route::get('jadwal-tambahan', [JadwalTambahanController::class, 'index']);
    Route::post('jadwal-tambahan', [JadwalTambahanController::class, 'store']);
    Route::post('jadwal-tambahan/approve', [JadwalTambahanController::class, 'approve']);
    
    // Pegawai (Employee) Routes - search must come before resource
    Route::get('pegawai/search', [PegawaiController::class, 'search']);
    Route::get('pegawai/statistik', [PegawaiController::class, 'statistik']);
Route::get('pegawai/list', [PegawaiController::class, 'list']); // Simplified list for dropdown
    Route::apiResource('pegawai', PegawaiController::class);

    // Kualifikasi Staf Klinis Routes
    Route::get('kualifikasi-staf', [\App\Http\Controllers\v2\KualifikasiStafController::class, 'index']);
    Route::get('kualifikasi-staf/{nik}', [\App\Http\Controllers\v2\KualifikasiStafController::class, 'show']);
    Route::post('kualifikasi-staf', [\App\Http\Controllers\v2\KualifikasiStafController::class, 'store']);
    Route::put('kualifikasi-staf/{nik}', [\App\Http\Controllers\v2\KualifikasiStafController::class, 'update']);
    Route::delete('kualifikasi-staf/{nik}', [\App\Http\Controllers\v2\KualifikasiStafController::class, 'destroy']);
});
