<?php

namespace App\Http\Controllers;

use App\Models\RsiaMenu;
use App\Models\RsiaRoleMenu;
use App\Models\RsiaRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MenuController extends Controller
{
    /**
     * Get all menus for admin management
     */
    public function index(Request $request)
    {
        try {
            $query = RsiaMenu::with(['parent', 'children']);

            // Filter by active status
            if ($request->has('active')) {
                $query->where('is_active', $request->boolean('active'));
            }

            // Filter by parent
            if ($request->has('parent_id')) {
                $query->where('parent_id', $request->parent_id);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nama_menu', 'like', "%{$search}%")
                      ->orWhere('route', 'like', "%{$search}%");
                });
            }

            $menus = $query->ordered()->get();

            return response()->json([
                'success' => true,
                'data' => $menus,
                'total' => $menus->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get menu tree structure
     */
    public function getTree(Request $request)
    {
        try {
            $onlyActive = $request->boolean('active', true);
            $menus = RsiaMenu::getMenuTree($onlyActive);

            return response()->json([
                'success' => true,
                'data' => $menus
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user menus based on their role
     */
    /**
     * Get user menus based on their role
     */
    public function getUserMenus(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not authenticated'
                ], 401);
            }

            $roleId = null;
            $roleName = null;

            // 1. Check if X-Role-ID header is present (Client-side selection)
            $headerRoleId = $request->header('X-Role-ID');
            if ($headerRoleId) {
                // Verify user has this role and it's assigned
                $assignment = DB::table('rsia_user_role')
                    ->join('rsia_role', 'rsia_user_role.id_role', '=', 'rsia_role.id_role')
                    ->where('rsia_user_role.id_user', $user->id_user)
                    ->where('rsia_user_role.id_role', $headerRoleId)
                    ->where('rsia_role.is_active', 1)
                    ->select('rsia_role.id_role', 'rsia_role.nama_role')
                    ->first();

                if ($assignment) {
                    $roleId = $assignment->id_role;
                    $roleName = $assignment->nama_role;
                }
            }

            // 2. If no valid header role, fallback to first active role in DB
            if (!$roleId) {
                $activeRole = DB::table('rsia_user_role')
                    ->join('rsia_role', 'rsia_user_role.id_role', '=', 'rsia_role.id_role')
                    ->where('rsia_user_role.id_user', $user->id_user)
                    ->where('rsia_user_role.is_active', 1)
                    ->where('rsia_role.is_active', 1)
                    ->select('rsia_role.id_role', 'rsia_role.nama_role')
                    ->first();

                if ($activeRole) {
                    $roleId = $activeRole->id_role;
                    $roleName = $activeRole->nama_role;
                }
            }

            if (!$roleId) {
                return response()->json([
                    'success' => false,
                    'error' => 'No active role found for user'
                ], 403);
            }

            // 3. Get menus directly from rsia_role_menu -> rsia_menu
            // This ensures we get menus SPECIFIC to the selected role id
            \Illuminate\Support\Facades\Log::info('getUserMenus Request', [
                'user_id' => $user->id_user,
                'determined_role_id' => $roleId
            ]);

            $menus = DB::table('rsia_role_menu as rm')
                ->join('rsia_menu as m', 'rm.id_menu', '=', 'm.id_menu')
                ->where('rm.id_role', $roleId)
                ->where('m.is_active', 1)
                ->where('rm.can_view', 1)
                ->select(
                    'm.id_menu',
                    'm.parent_id',
                    'm.nama_menu',
                    'm.icon',
                    'm.route',
                    'm.urutan',
                    'rm.can_view',
                    'rm.can_create',
                    'rm.can_update',
                    'rm.can_delete',
                    'rm.can_export',
                    'rm.can_import'
                )
                ->orderBy('m.urutan', 'asc')
                ->get();
            
            \Illuminate\Support\Facades\Log::info('getUserMenus Result', [
                'count' => $menus->count(),
                'ids' => $menus->pluck('id_menu')
            ]);

            // Build menu tree structure
            $menuTree = $this->buildMenuTree($menus);

            return response()->json([
                'success' => true,
                'data' => $menuTree,
                'user_role' => [
                    'id_role' => $roleId,
                    'nama_role' => $roleName
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new menu
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nama_menu' => 'required|string|max:100',
                'icon' => 'nullable|string|max:50',
                'route' => 'nullable|string|max:100',
                'parent_id' => 'nullable|integer|exists:rsia_menu,id_menu',
                'urutan' => 'nullable|integer|min:0',
                'is_active' => 'boolean'
            ]);

            // If parent_id is provided, check if it exists and is not creating circular reference
            if ($request->parent_id) {
                $this->checkCircularReference($request->parent_id, null);
            }

            DB::beginTransaction();

            $menu = RsiaMenu::create([
                'nama_menu' => $request->nama_menu,
                'icon' => $request->icon,
                'route' => $request->route,
                'parent_id' => $request->parent_id,
                'urutan' => $request->urutan ?? 0,
                'is_active' => $request->boolean('is_active', true),
                'created_by' => Auth::id()
            ]);

            // Update order if needed
            $this->updateMenuOrder($menu);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $menu,
                'message' => 'Menu berhasil dibuat'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Show specific menu
     */
    public function show($id)
    {
        try {
            $menu = RsiaMenu::with(['parent', 'children', 'roles'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $menu
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Menu not found'
            ], 404);
        }
    }

    /**
     * Update menu
     */
    public function update(Request $request, $id)
    {
        try {
            $menu = RsiaMenu::findOrFail($id);

            $request->validate([
                'nama_menu' => 'required|string|max:100',
                'icon' => 'nullable|string|max:50',
                'route' => 'nullable|string|max:100',
                'parent_id' => 'nullable|integer|exists:rsia_menu,id_menu',
                'urutan' => 'nullable|integer|min:0',
                'is_active' => 'boolean'
            ]);

            // Check circular reference if parent_id is being changed
            if ($request->parent_id && $request->parent_id != $menu->parent_id) {
                $this->checkCircularReference($request->parent_id, $id);
            }

            DB::beginTransaction();

            $menu->update([
                'nama_menu' => $request->nama_menu,
                'icon' => $request->icon,
                'route' => $request->route,
                'parent_id' => $request->parent_id,
                'urutan' => $request->urutan ?? $menu->urutan,
                'is_active' => $request->boolean('is_active', $menu->is_active),
                'updated_by' => Auth::id()
            ]);

            // Update order if needed
            $this->updateMenuOrder($menu);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $menu->fresh(['parent', 'children']),
                'message' => 'Menu berhasil diupdate'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Delete menu
     */
    public function destroy($id)
    {
        try {
            $menu = RsiaMenu::findOrFail($id);

            // Check if menu has children
            if ($menu->children()->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tidak bisa hapus menu yang memiliki sub-menu'
                ], 422);
            }

            // Check if menu is used in role permissions
            $roleMenusCount = RsiaRoleMenu::where('id_menu', $id)->count();
            if ($roleMenusCount > 0) {
                return response()->json([
                    'success' => false,
                    'error' => "Menu digunakan di {$roleMenusCount} role permissions"
                ], 422);
            }

            DB::beginTransaction();

            $menu->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Menu berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder menus
     */
    public function reorder(Request $request)
    {
        try {
            $request->validate([
                'menu_order' => 'required|array',
                'menu_order.*.id_menu' => 'required|integer|exists:rsia_menu,id_menu',
                'menu_order.*.urutan' => 'required|integer|min:0'
            ]);

            DB::beginTransaction();

            foreach ($request->menu_order as $item) {
                RsiaMenu::where('id_menu', $item['id_menu'])
                    ->update(['urutan' => $item['urutan']]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Menu order updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Build menu tree structure
     */
    private function buildMenuTree($menus)
    {
        $tree = [];
        $indexed = [];

        // Index menus by id
        foreach ($menus as $menu) {
            $indexed[$menu->id_menu] = (array) $menu;
            $indexed[$menu->id_menu]['children'] = [];
        }

        // Build tree structure
        foreach ($indexed as $id => &$menu) {
            if ($menu['parent_id'] && isset($indexed[$menu['parent_id']])) {
                $indexed[$menu['parent_id']]['children'][] = &$menu;
            } else {
                $tree[] = &$menu;
            }
        }

        // Remove empty children arrays
        $this->removeEmptyChildren($tree);

        return $tree;
    }

    /**
     * Remove empty children arrays
     */
    private function removeEmptyChildren(&$items)
    {
        foreach ($items as &$item) {
            if (empty($item['children'])) {
                unset($item['children']);
            } else {
                $this->removeEmptyChildren($item['children']);
            }
        }
    }

    /**
     * Check for circular reference in menu hierarchy
     */
    private function checkCircularReference($parentId, $excludeId = null)
    {
        $visited = [];
        $currentId = $parentId;

        while ($currentId && !in_array($currentId, $visited)) {
            if ($currentId == $excludeId) {
                throw new \Exception('Circular reference detected in menu hierarchy');
            }
            $visited[] = $currentId;

            $parent = RsiaMenu::find($currentId);
            $currentId = $parent ? $parent->parent_id : null;
        }
    }

    /**
     * Update menu order to ensure unique ordering
     */
    private function updateMenuOrder($menu)
    {
        $siblings = RsiaMenu::where('parent_id', $menu->parent_id)
            ->where('id_menu', '!=', $menu->id_menu)
            ->where('urutan', '>=', $menu->urutan)
            ->get();

        foreach ($siblings as $sibling) {
            $sibling->increment('urutan');
        }
    }

    /**
     * Get role permissions for all menus
     */
    public function getRolePermissions($roleId)
    {
        try {
            $role = RsiaRole::findOrFail($roleId);

            // Get all menus
            $menus = RsiaMenu::orderBy('parent_id')
                          ->orderBy('urutan')
                          ->get();

            // Get existing permissions for this role
            $existingPermissions = RsiaRoleMenu::where('id_role', $roleId)
                ->get()
                ->keyBy('id_menu');

            // Build menu tree with permissions
            $menuTree = $this->buildMenuTreeWithPermissions($menus, $existingPermissions);

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => $role,
                    'menus' => $menuTree,
                    'permissions' => $existingPermissions
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update role permissions for menus
     */
    public function updateRolePermissions(Request $request, $roleId)
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

            $role = RsiaRole::findOrFail($roleId);

            DB::beginTransaction();

            // Delete existing permissions
            RsiaRoleMenu::where('id_role', $roleId)->delete();

            // Insert new permissions
            foreach ($request->permissions as $permission) {
                // Only insert if at least one permission is granted
                if ($permission['can_view'] || $permission['can_create'] ||
                    $permission['can_update'] || $permission['can_delete'] ||
                    $permission['can_export'] || $permission['can_import']) {

                    RsiaRoleMenu::create([
                        'id_role' => $roleId,
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
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role permissions updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get all roles with their menu permissions summary
     */
    public function getRoleMenuSummary()
    {
        try {
            $roles = RsiaRole::withCount(['roleMenus' => function($query) {
                $query->where('can_view', 1);
            }])->get();

            $roles->each(function($role) {
                $role->permission_count = RsiaRoleMenu::where('id_role', $role->id_role)
                    ->where(function($query) {
                        $query->where('can_create', 1)
                              ->orWhere('can_update', 1)
                              ->orWhere('can_delete', 1)
                              ->orWhere('can_export', 1)
                              ->orWhere('can_import', 1);
                    })->count();
            });

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
     * Get menu permissions for specific role (detailed)
     */
    public function getRoleMenuDetails($roleId)
    {
        try {
            $role = RsiaRole::findOrFail($roleId);

            $permissions = DB::table('rsia_role_menu as rm')
                ->join('rsia_menu as m', 'rm.id_menu', '=', 'm.id_menu')
                ->where('rm.id_role', $roleId)
                ->select(
                    'rm.*',
                    'm.nama_menu',
                    'm.route',
                    'm.parent_id',
                    'm.urutan'
                )
                ->orderBy('m.parent_id')
                ->orderBy('m.urutan')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => $role,
                    'permissions' => $permissions
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Copy permissions from one role to another
     */
    public function copyRolePermissions(Request $request)
    {
        try {
            $request->validate([
                'source_role_id' => 'required|integer|exists:rsia_role,id_role',
                'target_role_id' => 'required|integer|exists:rsia_role,id_role|different:source_role_id'
            ]);

            $sourceRoleId = $request->source_role_id;
            $targetRoleId = $request->target_role_id;

            DB::beginTransaction();

            // Delete existing permissions for target role
            RsiaRoleMenu::where('id_role', $targetRoleId)->delete();

            // Get source permissions
            $sourcePermissions = RsiaRoleMenu::where('id_role', $sourceRoleId)->get();

            // Copy to target role
            foreach ($sourcePermissions as $permission) {
                RsiaRoleMenu::create([
                    'id_role' => $targetRoleId,
                    'id_menu' => $permission->id_menu,
                    'can_view' => $permission->can_view,
                    'can_create' => $permission->can_create,
                    'can_update' => $permission->can_update,
                    'can_delete' => $permission->can_delete,
                    'can_export' => $permission->can_export,
                    'can_import' => $permission->can_import,
                    'created_by' => Auth::id()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permissions copied successfully',
                'copied_count' => $sourcePermissions->count()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Check if user has access to specific menu and permission
     */
    public function checkUserAccess(Request $request)
    {
        try {
            $request->validate([
                'menu_id' => 'required|integer|exists:rsia_menu,id_menu',
                'permission' => 'required|in:can_view,can_create,can_update,can_delete,can_export,can_import',
                'user_id' => 'nullable|string' // optional, defaults to authenticated user
            ]);

            $userId = $request->user_id ?? Auth::id();
            $menuId = $request->menu_id;
            $permission = $request->permission;

            // Get user's roles
            $userRoles = DB::table('rsia_user_role')
                ->where('id_user', $userId)
                ->where('is_active', 1)
                ->pluck('id_role');

            if ($userRoles->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'has_access' => false,
                    'message' => 'No active roles found'
                ]);
            }

            // Check permission across all user roles
            $hasAccess = DB::table('rsia_role_menu')
                ->whereIn('id_role', $userRoles)
                ->where('id_menu', $menuId)
                ->where($permission, 1)
                ->exists();

            return response()->json([
                'success' => true,
                'has_access' => $hasAccess
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build menu tree with permissions
     */
    private function buildMenuTreeWithPermissions($menus, $permissions)
    {
        $tree = [];
        $indexed = [];

        // Index menus by id and add permissions
        foreach ($menus as $menu) {
            $menuArray = $menu->toArray();
            $menuPermission = $permissions->get($menu->id_menu);

            if ($menuPermission) {
                $menuArray['permissions'] = [
                    'can_view' => $menuPermission->can_view,
                    'can_create' => $menuPermission->can_create,
                    'can_update' => $menuPermission->can_update,
                    'can_delete' => $menuPermission->can_delete,
                    'can_export' => $menuPermission->can_export,
                    'can_import' => $menuPermission->can_import
                ];
            } else {
                $menuArray['permissions'] = [
                    'can_view' => false,
                    'can_create' => false,
                    'can_update' => false,
                    'can_delete' => false,
                    'can_export' => false,
                    'can_import' => false
                ];
            }

            $menuArray['children'] = [];
            $indexed[$menu->id_menu] = $menuArray;
        }

        // Build tree structure
        foreach ($indexed as $id => &$menu) {
            if ($menu['parent_id'] && isset($indexed[$menu['parent_id']])) {
                $indexed[$menu['parent_id']]['children'][] = &$menu;
            } else {
                $tree[] = &$menu;
            }
        }

        // Remove empty children arrays
        $this->removeEmptyChildren($tree);

        return $tree;
    }
}