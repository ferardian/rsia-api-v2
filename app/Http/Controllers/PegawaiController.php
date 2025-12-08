<?php

namespace App\Http\Controllers;

use App\Models\RsiaPetugas;
use App\Models\RsiaUserRole;
use App\Models\RsiaRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PetugasController extends Controller
{
    /**
     * Get all petugas with optional filters
     */
    public function index(Request $request)
    {
        try {
            $query = RsiaPetugas::with(['jabatan', 'activeUserRole.role']);

            // Filter by active status
            if ($request->has('active')) {
                $query->where('status', $request->boolean('active') ? 1 : 0);
            }

            // Filter by gender
            if ($request->has('jk')) {
                $query->where('jk', $request->jk);
            }

            // Filter by jabatan
            if ($request->has('kd_jbtn')) {
                $query->where('kd_jbtn', $request->kd_jbtn);
            }

            // Search
            if ($request->has('search')) {
                $query->search($request->search);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            $petugas = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $petugas->items(),
                'pagination' => [
                    'current_page' => $petugas->currentPage(),
                    'per_page' => $petugas->perPage(),
                    'total' => $petugas->total(),
                    'last_page' => $petugas->lastPage()
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
     * Get specific petugas
     */
    public function show($nip)
    {
        try {
            $petugas = RsiaPetugas::with([
                'jabatan',
                'userRoles.role',
                'userRoles' => function($query) {
                    $query->where('is_active', true);
                }
            ])->findOrFail($nip);

            return response()->json([
                'success' => true,
                'data' => $petugas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Petugas not found'
            ], 404);
        }
    }

    /**
     * Search petugas
     */
    public function search(Request $request)
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2'
            ]);

            $query = $request->get('q');
            $limit = $request->get('limit', 20);

            $petugas = RsiaPetugas::search($query)
                ->active()
                ->limit($limit)
                ->get(['nip', 'nama', 'kd_jbtn', 'jk']);

            return response()->json([
                'success' => true,
                'data' => $petugas,
                'total' => $petugas->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Assign role to petugas
     */
    public function assignRole(Request $request)
    {
        try {
            $request->validate([
                'nip' => 'required|string|exists:rsia_petugas,nip',
                'role_id' => 'required|integer|exists:rsia_role,id_role',
                'user_id' => 'required|string'
            ]);

            // Check if petugas exists
            $petugas = RsiaPetugas::findOrFail($request->nip);

            // Check if role exists
            $role = RsiaRole::findOrFail($request->role_id);

            // Check if user already has this role
            $existingRole = RsiaUserRole::where('nip', $request->nip)
                ->where('id_role', $request->role_id)
                ->where('is_active', true)
                ->first();

            if ($existingRole) {
                return response()->json([
                    'success' => false,
                    'error' => 'Petugas already has this role'
                ], 422);
            }

            DB::beginTransaction();

            // Deactivate existing roles for this user if any
            RsiaUserRole::where('id_user', $request->user_id)
                ->update(['is_active' => false]);

            // Create new user role assignment
            $userRole = RsiaUserRole::create([
                'id_user' => $request->user_id,
                'id_role' => $request->role_id,
                'nip' => $request->nip,
                'is_active' => true,
                'created_by' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $userRole->load(['role', 'petugas']),
                'message' => 'Role assigned successfully'
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
     * Remove role from petugas
     */
    public function removeRole($nip, $roleId)
    {
        try {
            $userRole = RsiaUserRole::where('nip', $nip)
                ->where('id_role', $roleId)
                ->firstOrFail();

            DB::beginTransaction();

            // Deactivate instead of delete for audit trail
            $userRole->update([
                'is_active' => false,
                'updated_by' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role removed successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get petugas roles
     */
    public function getRoles($nip)
    {
        try {
            $petugas = RsiaPetugas::findOrFail($nip);

            $roles = RsiaUserRole::where('nip', $nip)
                ->where('is_active', true)
                ->with(['role'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $roles,
                'petugas' => $petugas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Petugas not found'
            ], 404);
        }
    }

    /**
     * Get petugas statistics
     */
    public function getStatistics()
    {
        try {
            $stats = [
                'total_petugas' => RsiaPetugas::count(),
                'active_petugas' => RsiaPetugas::active()->count(),
                'petugas_with_roles' => RsiaUserRole::where('is_active', true)
                    ->distinct('nip')->count('nip'),
                'total_roles_assigned' => RsiaUserRole::where('is_active', true)->count(),
                'by_gender' => [
                    'laki-laki' => RsiaPetugas::laki()->count(),
                    'perempuan' => RsiaPetugas::perempuan()->count()
                ],
                'by_jabatan' => DB::table('rsia_petugas')
                    ->join('rsia_jabatan', 'rsia_petugas.kd_jbtn', '=', 'rsia_jabatan.kd_jbtn')
                    ->where('rsia_petugas.status', 1)
                    ->select('rsia_jabatan.nama_jbtn', DB::raw('COUNT(*) as count'))
                    ->groupBy('rsia_jabatan.nama_jbtn')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}