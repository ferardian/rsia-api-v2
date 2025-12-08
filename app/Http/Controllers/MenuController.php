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

            // Get user's active role
            $userRole = DB::table('rsia_user_role')
                ->join('rsia_role', 'rsia_user_role.id_role', '=', 'rsia_role.id_role')
                ->where('rsia_user_role.id_user', $user->id_user)
                ->where('rsia_user_role.is_active', true)
                ->where('rsia_role.is_active', true)
                ->first();

            if (!$userRole) {
                return response()->json([
                    'success' => false,
                    'error' => 'No active role found'
                ], 403);
            }

            // Get menus with permissions for this user's role
            $menus = DB::table('v_user_menu_permissions')
                ->where('id_user', $user->id_user)
                ->orderBy('parent_id')
                ->orderBy('urutan')
                ->get();

            // Build menu tree structure
            $menuTree = $this->buildMenuTree($menus);

            return response()->json([
                'success' => true,
                'data' => $menuTree,
                'user_role' => [
                    'id_role' => $userRole->id_role,
                    'nama_role' => $userRole->nama_role
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
}