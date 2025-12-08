<?php

use App\Http\Controllers\MenuController;
use App\Http\Controllers\RoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| RSIA Menu Management API Routes
|--------------------------------------------------------------------------
|
| Routes for managing hospital menus, user roles, permissions, and staff
|
*/

// Menu Management Routes
Route::prefix('menu')->group(function () {
    Route::get('/', [MenuController::class, 'index']);
    Route::get('/tree', [MenuController::class, 'getTree']);
    Route::get('/user', [MenuController::class, 'getUserMenus'])->middleware('auth:aes');
    Route::post('/create', [MenuController::class, 'store'])->middleware('auth:aes');
    Route::get('/{id}', [MenuController::class, 'show']);
    Route::put('/{id}/update', [MenuController::class, 'update'])->middleware('auth:aes');
    Route::delete('/{id}/delete', [MenuController::class, 'destroy'])->middleware('auth:aes');
    Route::post('/reorder', [MenuController::class, 'reorder'])->middleware('auth:aes');
});

// Role/Access Level Management Routes
Route::prefix('access-level')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('/with-petugas', [RoleController::class, 'getWithPetugas']);
    Route::post('/create', [RoleController::class, 'store'])->middleware('auth:aes');
    Route::get('/{id}', [RoleController::class, 'show']);
    Route::put('/{id}/update', [RoleController::class, 'update'])->middleware('auth:aes');
    Route::delete('/{id}/delete', [RoleController::class, 'destroy'])->middleware('auth:aes');

    // Role Permissions
    Route::get('/{id}/permissions', [RoleController::class, 'getPermissions']);
    Route::post('/{id}/permissions', [RoleController::class, 'updatePermissions'])->middleware('auth:aes');
});

// User Access Management Routes
Route::prefix('user-access')->group(function () {
    Route::get('/', [RoleController::class, 'getAllUserRoles'])->middleware('auth:aes');
});

// Pegawai Routes
Route::prefix('pegawai')->group(function () {
    // Route pegawai utama sudah dihandle di routes/partials/pegawai.php
    // dengan PegawaiController v2 yang sudah ada LEFT JOIN yang benar
    // Route ini dihapus untuk menghindari konflik

    Route::get('/search', function (Request $request) {
        try {
            $query = $request->get('q');
            $pegawai = DB::table('petugas')
                ->select('nip', 'nama')
                ->where('nama', 'like', "%{$query}%")
                ->orWhere('nip', 'like', "%{$query}%")
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $pegawai
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Gagal mencari pegawai: ' . $e->getMessage()
            ], 500);
        }
    });

    Route::get('/{nip}', function ($nip) {
        try {
            $pegawai = DB::table('petugas')
                ->select('nip', 'nama')
                ->where('nip', $nip)
                ->first();

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pegawai tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $pegawai
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Gagal mengambil detail pegawai: ' . $e->getMessage()
            ], 500);
        }
    });

    Route::post('/role/assign', function (Request $request) {
        try {
            // Log incoming request for debugging
            \Log::info('Role assignment request:', $request->all());

            $validated = $request->validate([
                'nip' => 'required|string',
                'access_level_id' => 'required|integer',
                'user_id' => 'required|integer'
            ]);

            \Log::info('Role assignment validated data:', $validated);

            // Check if user already has any role by NIP (primary identifier for pegawai)
            $existingRole = \App\Models\RsiaUserRole::where('nip', $request->nip)->first();

            if ($existingRole) {
                // Update existing role (each pegawai can only have one role)
                if ($existingRole->id_role == $request->access_level_id) {
                    \Log::info('Pegawai already has this role:', $existingRole->toArray());
                    $message = 'User sudah memiliki role ini';
                    $statusCode = 200;
                } else {
                    // Update to new role (update user_id if needed)
                    $existingRole->update([
                        'id_user' => $request->user_id, // Update user_id to match current login
                        'id_role' => $request->access_level_id,
                        'is_active' => true,
                        'updated_by' => $request->user_id
                    ]);
                    \Log::info('Pegawai role updated:', $existingRole->toArray());
                    $message = 'Role berhasil diperbarui';
                    $statusCode = 200;
                }
                $userRole = $existingRole;
            } else {
                // Create new role assignment (NIP doesn't exist yet)
                try {
                    $userRole = \App\Models\RsiaUserRole::create([
                        'nip' => $request->nip,
                        'id_role' => $request->access_level_id,
                        'id_user' => $request->user_id,
                        'is_active' => true,
                        'created_by' => $request->user_id,
                        'updated_by' => $request->user_id
                    ]);
                    \Log::info('New role assignment created:', $userRole->toArray());
                    $message = 'Role berhasil ditugaskan ke pegawai';
                    $statusCode = 201;
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle unique constraint violation - try updating by user_id
                    if ($e->getCode() == 23000 && strpos($e->getMessage(), 'unique_user') !== false) {
                        \Log::warning('Unique user constraint violated, trying to update existing record by user_id');

                        $existingByUserId = \App\Models\RsiaUserRole::where('id_user', $request->user_id)->first();
                        if ($existingByUserId) {
                            $existingByUserId->update([
                                'nip' => $request->nip, // Update NIP to correct pegawai
                                'id_role' => $request->access_level_id,
                                'is_active' => true,
                                'updated_by' => $request->user_id
                            ]);
                            \Log::info('Updated existing record by user_id:', $existingByUserId->toArray());
                            $userRole = $existingByUserId;
                            $message = 'Role berhasil diperbarui';
                            $statusCode = 200;
                        } else {
                            throw $e;
                        }
                    } else {
                        throw $e;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $userRole->load('role')
            ], $statusCode);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Role assignment validation error:', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'messages' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Role assignment error:', [
                'message' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Gagal menugaskan role: ' . $e->getMessage()
            ], 500);
        }
    })->middleware('auth:aes');

    Route::delete('/{nip}/access/{roleId}', function ($nip, $roleId) {
        try {
            $deleted = DB::table('rsia_user_role')
                ->where('nip', $nip)
                ->where('id_role', $roleId)
                ->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Role assignment tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role berhasil dihapus dari pegawai'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Gagal menghapus role: ' . $e->getMessage()
            ], 500);
        }
    })->middleware('auth:aes');

    Route::get('/{nip}/roles', function ($nip) {
        try {
            $roles = DB::table('rsia_user_role as ur')
                ->select('ur.*', 'r.nama_role')
                ->join('rsia_role as r', 'ur.id_role', '=', 'r.id_role')
                ->where('ur.nip', $nip)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $roles
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Gagal mengambil role pegawai: ' . $e->getMessage()
            ], 500);
        }
    });
});

// Permission Check Route (for dynamic checking)
Route::get('/check-access/{menuId}/{permission}', function ($menuId, $permission) {
    $user = request()->user();

    if (!$user) {
        return response()->json(['has_access' => false], 401);
    }

    // Check if user has permission via their role
    $hasPermission = \DB::table('v_user_menu_permissions')
        ->where('id_user', $user->id_user)
        ->where('id_menu', $menuId)
        ->where($permission, true)
        ->exists();

    return response()->json([
        'has_access' => $hasPermission,
        'menu_id' => $menuId,
        'permission' => $permission
    ]);
})->middleware('auth:aes');