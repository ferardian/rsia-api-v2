<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MenuController;

// Menu Management Routes
Route::middleware(['auth:aes'])->prefix('menu-management')->group(function () {

    // Master Menu Management
    Route::prefix('menus')->group(function () {
        Route::get('/', [MenuController::class, 'index']);
        Route::get('/tree', [MenuController::class, 'getTree']);
        Route::post('/', [MenuController::class, 'store']);
        Route::get('/{id}', [MenuController::class, 'show']);
        Route::put('/{id}', [MenuController::class, 'update']);
        Route::delete('/{id}', [MenuController::class, 'destroy']);
        Route::post('/reorder', [MenuController::class, 'reorder']);
    });

    // Role-Menu Permission Management
    Route::prefix('role-permissions')->group(function () {
        // Get all roles with menu permissions summary
        Route::get('/summary', [MenuController::class, 'getRoleMenuSummary']);

        // Role specific permissions
        Route::get('/role/{roleId}', [MenuController::class, 'getRolePermissions']);
        Route::get('/role/{roleId}/details', [MenuController::class, 'getRoleMenuDetails']);
        Route::post('/role/{roleId}', [MenuController::class, 'updateRolePermissions']);

        // Copy permissions between roles
        Route::post('/copy', [MenuController::class, 'copyRolePermissions']);

        // Check user access to specific menu
        Route::post('/check-access', [MenuController::class, 'checkUserAccess']);
    });

    // User Menus (for frontend navigation)
    Route::get('/user-menus', [MenuController::class, 'getUserMenus']);

});

// Legacy menu routes (backward compatibility)
Route::middleware(['auth:aes'])->prefix('menu')->group(function () {
    Route::get('/', [MenuController::class, 'index']);
    Route::get('/tree', [MenuController::class, 'getTree']);
    Route::get('/user', [MenuController::class, 'getUserMenus']);
    Route::post('/', [MenuController::class, 'store']);
    Route::get('/{id}', [MenuController::class, 'show']);
    Route::put('/{id}', [MenuController::class, 'update']);
    Route::delete('/{id}', [MenuController::class, 'destroy']);
    Route::post('/reorder', [MenuController::class, 'reorder']);
    Route::get('/check-access/{menuId}/{permission}', [MenuController::class, 'checkAccess']);
});