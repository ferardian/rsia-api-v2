<?php

use Orion\Facades\Orion;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:aes', 'claim:role,pegawai|dokter|IT|admin|direksi'])->prefix('sdi/master')->group(function () {
    Orion::resource('departemen', \App\Http\Controllers\Orion\DepartemenController::class)->only('search');
    Orion::resource('jnj-jabatan', \App\Http\Controllers\Orion\JnjJabatanController::class)->only('search');
    Orion::resource('pendidikan', \App\Http\Controllers\Orion\PendidikanController::class)->only('search');
    Orion::resource('stts-kerja', \App\Http\Controllers\Orion\SttsKerjaController::class)->only('search');
    Orion::resource('stts-wp', \App\Http\Controllers\Orion\SttsWpController::class)->only('search');
    Orion::resource('bidang', \App\Http\Controllers\Orion\BidangController::class)->only('search');
    Orion::resource('kelompok-jabatan', \App\Http\Controllers\Orion\KelompokJabatanController::class)->only('search');
    Orion::resource('resiko-kerja', \App\Http\Controllers\Orion\ResikoKerjaController::class)->only('search');
    Orion::resource('emergency-index', \App\Http\Controllers\Orion\EmergencyIndexController::class)->only('search');
    Orion::resource('bank', \App\Http\Controllers\Orion\BankController::class)->only('search');
    Orion::resource('jabatan', \App\Http\Controllers\Orion\JabatanController::class)->only('search');
});
