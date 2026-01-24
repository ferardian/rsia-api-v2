<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\v2\PegawaiController;
use App\Http\Controllers\v2\LegacyUserController;

// User Management Routes
Route::middleware(['auth:aes'])->prefix('user-management')->group(function () {

    // Role Management Routes
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::get('/with-petugas', [RoleController::class, 'getWithPetugas']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::put('/{id}', [RoleController::class, 'update']);
        Route::delete('/{id}', [RoleController::class, 'destroy']);
        Route::get('/{id}/permissions', [RoleController::class, 'getPermissions']);
        Route::post('/{id}/permissions', [RoleController::class, 'updatePermissions']);
    });

    // User Role Assignment Routes
    Route::prefix('user-access')->group(function () {
        Route::get('/', [RoleController::class, 'getAllUserRoles']);
        Route::post('/assign', [RoleController::class, 'assignRoleToUser']);
        Route::delete('/{nip}/role/{roleId}', [RoleController::class, 'removeRoleFromUser']);
    });

    // Pegawai Management Routes
    Route::prefix('pegawai')->group(function () {
        Route::get('/', [PegawaiController::class, 'index']);
        Route::get('/search', [PegawaiController::class, 'search']);
        Route::get('/{nip}', [PegawaiController::class, 'show']);
        Route::get('/{nip}/roles', [PegawaiController::class, 'getUserRoles']);
        Route::post('/{nip}/role/assign', [PegawaiController::class, 'assignRole']);
        Route::delete('/{nip}/role/{roleId}', [PegawaiController::class, 'removeRole']);
        Route::get('/statistics', [PegawaiController::class, 'getStatistics']);
    });

    // Legacy User (sikrsia.user table) Routes
    Route::get('legacy-users', [LegacyUserController::class, 'index']);
    Route::post('legacy-users/{id_user}/set-password', [LegacyUserController::class, 'setPassword']);
    Route::get('legacy-users/{id_user}/check', [LegacyUserController::class, 'checkUser']);
    Route::get('legacy-users/{id_user}/password', [LegacyUserController::class, 'getPassword']);

});
