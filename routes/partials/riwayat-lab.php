<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RiwayatLabController;

// Rute untuk riwayat pemeriksaan laboratorium
Route::middleware(['auth:api', 'detail-user'])->prefix('riwayat-lab')->group(function () {

    /**
     * Get riwayat lengkap pemeriksaan lab pasien
     *
     * Query Parameters:
     * - no_rkm_medis (required): Nomor rekam medis pasien
     * - limit (optional): Jumlah data per halaman, default 20, max 100
     * - page (optional): Halaman saat ini, default 1
     * - tanggal_dari (optional): Filter tanggal mulai pemeriksaan (Y-m-d format)
     * - tanggal_sampai (optional): Filter tanggal akhir pemeriksaan (Y-m-d format)
     *
     * Example: /api/riwayat-lab?no_rkm_medis=123456&limit=10&page=1&tanggal_dari=2024-01-01&tanggal_sampai=2024-12-31
     */
    Route::get('/', [RiwayatLabController::class, 'getRiwayatLab']);

    /**
     * Get ringkasan riwayat pemeriksaan lab pasien (tanpa detail)
     *
     * Query Parameters:
     * - no_rkm_medis (required): Nomor rekam medis pasien
     * - limit (optional): Jumlah data yang ingin ditampilkan, default 10, max 50
     *
     * Example: /api/riwayat-lab/ringkasan?no_rkm_medis=123456&limit=5
     */
    Route::get('/ringkasan', [RiwayatLabController::class, 'getRingkasanRiwayatLab']);
});