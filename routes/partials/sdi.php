<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\JadwalPegawaiController;
use App\Http\Controllers\v2\PegawaiController;
use App\Http\Controllers\v2\JadwalTambahanController;
use App\Http\Controllers\v2\ScheduleGeneratorController;
use App\Http\Controllers\v2\DokterController;
use App\Http\Controllers\v2\MappingJabatanController;

Route::middleware(['auth:aes', 'claim:role,pegawai|dokter|IT|admin|direksi'])->prefix('sdi')->group(function () {
    // Jadwal Pegawai Routes
    Route::get('jadwal-pegawai', [JadwalPegawaiController::class, 'index']);
    Route::get('temporary-presensi', [\App\Http\Controllers\v2\RsiaTemporaryPresensiController::class, 'index']);
    Route::get('rekap-cuti', [\App\Http\Controllers\v2\CutiPegawaiController::class, 'rekapCuti']);
    Route::post('jadwal-pegawai', [JadwalPegawaiController::class, 'store']);
    Route::post('jadwal-pegawai/admin', [JadwalPegawaiController::class, 'storeAdmin']);
    Route::post('jadwal-pegawai/approve', [JadwalPegawaiController::class, 'approve']);
    Route::post('jadwal-pegawai/approve', [JadwalPegawaiController::class, 'approve']);
    Route::get('shifts', [JadwalPegawaiController::class, 'getShifts']);
    Route::post('jadwal-pegawai/generate', [ScheduleGeneratorController::class, 'generate']);


    // Jadwal Tambahan Routes
    Route::get('jadwal-tambahan', [JadwalTambahanController::class, 'index']);
    Route::post('jadwal-tambahan', [JadwalTambahanController::class, 'store']);
    Route::post('jadwal-tambahan/approve', [JadwalTambahanController::class, 'approve']);
    
    // Pegawai (Employee) Routes - search must come before resource
    Route::get('pegawai/search', [PegawaiController::class, 'search']);
    Route::get('pegawai/statistik', [PegawaiController::class, 'statistik']);
    Route::get('pegawai/list', [PegawaiController::class, 'list']); // Simplified list for dropdown
    Route::get('pegawai/tanpa-email', [PegawaiController::class, 'tanpaEmail']);
    Route::post('pegawai/update-email', [PegawaiController::class, 'updateEmail']);
    Route::post('pegawai/update-profile', [PegawaiController::class, 'updateProfile']);
    Route::apiResource('pegawai', PegawaiController::class);
    
    // Berkas Pegawai (Legacy Parity)
    Route::post('pegawai/get/berkas', [\App\Http\Controllers\v2\BerkasPegawaiController::class, 'getBerkas']);
    Route::get('pegawai/berkas/kategori', [\App\Http\Controllers\v2\BerkasPegawaiController::class, 'getBerkasKategori']);
    Route::get('pegawai/berkas/nama-berkas', [\App\Http\Controllers\v2\BerkasPegawaiController::class, 'getNamaBerkas']);
    Route::post('pegawai/upload/berkas', [\App\Http\Controllers\v2\BerkasPegawaiController::class, 'uploadBerkas']);
    Route::post('pegawai/delete/berkas', [\App\Http\Controllers\v2\BerkasPegawaiController::class, 'deleteBerkas']);

    // Dokter (Doctor) Routes - search must come before resource
    Route::get('dokter/spesialisasi', [DokterController::class, 'getSpesialisasi']);
    Route::get('dokter/search', [DokterController::class, 'search']);
    Route::apiResource('dokter', DokterController::class);

    // Kualifikasi Staf Klinis Routes
    Route::get('kualifikasi-staf', [\App\Http\Controllers\v2\KualifikasiStafController::class, 'index']);
    Route::get('kualifikasi-staf/{nik}', [\App\Http\Controllers\v2\KualifikasiStafController::class, 'show']);
    Route::post('kualifikasi-staf', [\App\Http\Controllers\v2\KualifikasiStafController::class, 'store']);
    Route::put('kualifikasi-staf/{nik}', [\App\Http\Controllers\v2\KualifikasiStafController::class, 'update']);
    Route::delete('kualifikasi-staf/{nik}', [\App\Http\Controllers\v2\KualifikasiStafController::class, 'destroy']);

    // Unit Shift Rules (Orion REST API)
    Orion::resource('unit-shift-rules', \App\Http\Controllers\Orion\RsiaUnitShiftRuleController::class);
    
    Route::get('departemen/next-id', [\App\Http\Controllers\Orion\DepartemenController::class, 'generateNextId']);
    Orion::resource('departemen', \App\Http\Controllers\Orion\DepartemenController::class);

    // Committee Routes
    Route::get('committees', [\App\Http\Controllers\v2\CommitteeController::class, 'index']);
    Route::get('committees/anggota', [\App\Http\Controllers\v2\CommitteeController::class, 'indexMembers']);
    Route::get('committees/pegawai/{nik}', [\App\Http\Controllers\v2\CommitteeController::class, 'getByNik']);
    Route::post('committees/anggota', [\App\Http\Controllers\v2\CommitteeController::class, 'store']);
    Route::put('committees/anggota/{id}', [\App\Http\Controllers\v2\CommitteeController::class, 'update']);
    Route::delete('committees/anggota/{id}', [\App\Http\Controllers\v2\CommitteeController::class, 'destroy']);

    Route::get('mapping-jabatan/jabatan-list', [MappingJabatanController::class, 'getJabatanList']);
    Route::resource('mapping-jabatan', MappingJabatanController::class);
});
