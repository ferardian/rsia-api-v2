<?php
/**
 * Created by Antigravity.
 * User: Ferry Ardiansyah
 * Date: 2026-01-15
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\v2\HelpdeskController;

Route::prefix('helpdesk')->middleware(['user-aes', 'claim:role,pegawai'])->group(function () {
    Route::get('/tiket', [HelpdeskController::class, 'index']);
    Route::put('/tiket/{id}/status', [HelpdeskController::class, 'updateStatus']);
    
    // Tiket Lanjutan
    Route::get('/tiket/active', [HelpdeskController::class, 'getTickets']);
    Route::post('/tiket/create', [HelpdeskController::class, 'createTicketFromLog']);
    Route::put('/tiket/{id}/update', [HelpdeskController::class, 'updateTicket']);
});
