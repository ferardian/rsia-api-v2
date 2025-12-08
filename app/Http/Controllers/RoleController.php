<?php

namespace App\Http\Controllers;

use App\Models\RsiaRole;
use App\Models\RsiaUserRole;
use App\Models\RsiaRoleMenu;
use App\Models\RsiaPetugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    /**
     * Get all roles
     */
    public function index(Request $request)
    {
        try {
            $query = RsiaRole::withCount(['userRoles' => function($query) {
                $query->where('is_active', true);
            }]);

            // Filter by active status
            if ($request->has('active')) {
                $query->where('is_active', $request->boolean('active'));
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nama_role', 'like', "%{$search}%")
                      ->orWhere('deskripsi', 'like', "%{$search}%");
                });
            }

            $roles = $query->get();

            return response()->json([
                'success' => true,
                'data' => $roles,
                'total' => $roles->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get role with petugas data
     */
    public function getWithPetugas()
    {
        try {
            $roles = DB::table('v_user_petugas')
                ->select(
                    'id_role',
                    'nama_role',
                    'deskripsi',
                    DB::raw('COUNT(*) as user_count'),
                    DB::raw('GROUP_CONCAT(DISTINCT nama_petugas ORDER BY nama_petugas SEPARATOR ", ") as users')
                )
                ->where('user_role_active', true)
                ->groupBy('id_role', 'nama_role', 'deskripsi')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $roles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all user-role assignments
     */
    public function getAllUserRoles()
    {
        try {
            // Optimized query with LEFT JOIN to get complete user role data
            $userRoles = DB::table('rsia_user_role as ur')
                ->leftJoin('pegawai as p', 'ur.nip', '=', 'p.nik')
                ->leftJoin('rsia_role as r', 'ur.id_role', '=', 'r.id_role')
                ->where('ur.is_active', 1)
                ->where(function($query) {
                    $query->where('p.stts_aktif', 'AKTIF')
                          ->orWhereNull('p.stts_aktif');
                })
                ->select([
                    'ur.nip as nip',
                    'ur.id_role',
                    'ur.id_user',
                    'ur.is_active',
                    'p.nama as nama_pegawai',
                    'p.jbtn as jabatan',
                    'r.nama_role'
                ])
                ->orderBy('r.nama_role')
                ->orderBy('p.nama')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $userRoles,
                'total' => $userRoles->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Gagal mengambil data user roles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show specific role
     */
    public function show($id)
    {
        try {
            $role = RsiaRole::with(['userRoles.petugas', 'roleMenus.menu'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $role
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Role not found'
            ], 404);
        }
    }

    /**
     * Create new role
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nama_role' => 'required|string|max:50|unique:rsia_role,nama_role',
                'deskripsi' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            $role = RsiaRole::create([
                'nama_role' => $request->nama_role,
                'deskripsi' => $request->deskripsi,
                'is_active' => $request->boolean('is_active', true),
                'created_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $role,
                'message' => 'Role berhasil dibuat'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Update role
     */
    public function update(Request $request, $id)
    {
        try {
            $role = RsiaRole::findOrFail($id);

            $request->validate([
                'nama_role' => 'required|string|max:50|unique:rsia_role,nama_role,' . $id . ',id_role',
                'deskripsi' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            $role->update([
                'nama_role' => $request->nama_role,
                'deskripsi' => $request->deskripsi,
                'is_active' => $request->boolean('is_active', $role->is_active),
                'updated_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'data' => $role->fresh(),
                'message' => 'Role berhasil diupdate'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Delete role
     */
    public function destroy($id)
    {
        try {
            $role = RsiaRole::findOrFail($id);

            // Check if role has active users
            $activeUsersCount = RsiaUserRole::where('id_role', $id)
                ->where('is_active', true)
                ->count();

            if ($activeUsersCount > 0) {
                return response()->json([
                    'success' => false,
                    'error' => "Tidak bisa hapus role yang digunakan oleh {$activeUsersCount} user aktif"
                ], 422);
            }

            // Delete role permissions first
            RsiaRoleMenu::where('id_role', $id)->delete();

            // Delete role
            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get role permissions
     */
    public function getPermissions($id)
    {
        try {
            $role = RsiaRole::findOrFail($id);

            $permissions = RsiaRoleMenu::where('id_role', $id)
                ->with('menu')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $permissions,
                'role' => $role
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update role permissions
     */
    public function updatePermissions(Request $request, $id)
    {
        try {
            $request->validate([
                'permissions' => 'required|array',
                'permissions.*.id_menu' => 'required|integer|exists:rsia_menu,id_menu',
                'permissions.*.can_view' => 'boolean',
                'permissions.*.can_create' => 'boolean',
                'permissions.*.can_update' => 'boolean',
                'permissions.*.can_delete' => 'boolean',
                'permissions.*.can_export' => 'boolean',
                'permissions.*.can_import' => 'boolean'
            ]);

            DB::beginTransaction();

            $role = RsiaRole::findOrFail($id);

            // Delete existing permissions
            RsiaRoleMenu::where('id_role', $id)->delete();

            // Insert new permissions
            foreach ($request->permissions as $permission) {
                RsiaRoleMenu::create([
                    'id_role' => $id,
                    'id_menu' => $permission['id_menu'],
                    'can_view' => $permission['can_view'] ?? false,
                    'can_create' => $permission['can_create'] ?? false,
                    'can_update' => $permission['can_update'] ?? false,
                    'can_delete' => $permission['can_delete'] ?? false,
                    'can_export' => $permission['can_export'] ?? false,
                    'can_import' => $permission['can_import'] ?? false,
                    'created_by' => Auth::id()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permissions updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }
}