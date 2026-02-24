<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\RsiaHfisSkJadwalController;

Route::prefix('sdi/hfis/sk-jadwal')->middleware(['detail-user', 'claim:role,pegawai|dokter'])->group(function () {
    Route::get('/', [RsiaHfisSkJadwalController::class, 'index']);
    Route::post('/', [RsiaHfisSkJadwalController::class, 'store']);
    Route::get('/{id}', [RsiaHfisSkJadwalController::class, 'show']);
    Route::put('/{id}', [RsiaHfisSkJadwalController::class, 'update']);
    Route::delete('/{id}', [RsiaHfisSkJadwalController::class, 'destroy']);
    Route::get('/{id}/pdf', [RsiaHfisSkJadwalController::class, 'generatePdf']);
    Route::post('/pdf/bulk', [RsiaHfisSkJadwalController::class, 'generatePdfBulk']);
    
    Route::get('/resource/poli-mappings', [RsiaHfisSkJadwalController::class, 'getHfismasterResources']);
    Route::post('/update-jadwal', [RsiaHfisSkJadwalController::class, 'updateHfis']);
    Route::get('/jadwal-dokter/{poli}/tanggal/{tanggal}', [RsiaHfisSkJadwalController::class, 'getJadwalDokterHfis']);
});
