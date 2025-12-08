<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CheckAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @param  string|null  $menuId
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission, string $menuId = null)
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required'
            ], 401);
        }

        // Get user's active access level/role
        $userAccess = DB::table('rsia_user_role')
            ->join('rsia_role', 'rsia_user_role.id_role', '=', 'rsia_role.id_role')
            ->where('rsia_user_role.id_user', $user->id_user)
            ->where('rsia_user_role.is_active', true)
            ->where('rsia_role.is_active', true)
            ->first();

        // Check if user has an active access level
        if (!$userAccess) {
            return response()->json([
                'success' => false,
                'error' => 'No active access level found'
            ], 403);
        }

        // Super admin bypass (access level id 1)
        if ($userAccess->id_role == 1) {
            return $next($request);
        }

        // If menuId is provided, check specific menu permission
        if ($menuId) {
            $hasPermission = DB::table('rsia_role_menu')
                ->join('rsia_menu', 'rsia_role_menu.id_menu', '=', 'rsia_menu.id_menu')
                ->where('rsia_role_menu.id_role', $userAccess->id_role)
                ->where('rsia_role_menu.id_menu', $menuId)
                ->where('rsia_role_menu.' . $permission, true)
                ->where('rsia_menu.is_active', true)
                ->exists();

            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'error' => 'Insufficient access permissions',
                    'required_permission' => $permission,
                    'menu_id' => $menuId
                ], 403);
            }
        } else {
            // Check if user has the permission for any menu
            $hasPermission = DB::table('rsia_role_menu')
                ->join('rsia_menu', 'rsia_role_menu.id_menu', '=', 'rsia_menu.id_menu')
                ->where('rsia_role_menu.id_role', $userAccess->id_role)
                ->where('rsia_role_menu.' . $permission, true)
                ->where('rsia_menu.is_active', true)
                ->exists();

            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'error' => 'Insufficient access permissions',
                    'required_permission' => $permission
                ], 403);
            }
        }

        return $next($request);
    }
}