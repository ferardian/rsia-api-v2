<?php

use App\Http\Controllers\v2\RsiaMappingPerformerRoleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Performer Role Mapping (Partials)
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v2')->group(function () {
    Route::prefix('performer-role-mapping')->group(function () {
        Route::get('/', [RsiaMappingPerformerRoleController::class, 'index']);
        Route::post('/', [RsiaMappingPerformerRoleController::class, 'store']);
        Route::get('/unmapped', [RsiaMappingPerformerRoleController::class, 'unmapped']);
        Route::post('/bulk-update', [RsiaMappingPerformerRoleController::class, 'bulkUpdate']);
        Route::get('/stats', [RsiaMappingPerformerRoleController::class, 'stats']);
        Route::get('/{id_petugas}', [RsiaMappingPerformerRoleController::class, 'show']);
        Route::put('/{id_petugas}', [RsiaMappingPerformerRoleController::class, 'update']);
        Route::delete('/{id_petugas}', [RsiaMappingPerformerRoleController::class, 'destroy']);
    });
});