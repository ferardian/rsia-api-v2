<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RsiaMappingPerformerRole;
use App\Models\Petugas;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RsiaMappingPerformerRoleController extends Controller
{
    /**
     * Display a listing of performer role mappings.
     *
     * @queryParam search string Search term for SNOMED code or display name. Example: 123456
     * @queryParam status string Filter by status (active, inactive, draft). Example: active
     * @queryParam petugas string Search by petugas name. Example: Dokter
     * @queryParam per_page int Items per page. Defaults to 15. Example: 10
     * @queryParam page int Page number. Defaults to 1. Example: 1
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Log incoming request details
        Log::info('RsiaMappingPerformerRoleController::index called', [
            'request_all' => $request->all(),
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'petugas' => $request->get('petugas')
        ]);

        $query = RsiaMappingPerformerRole::with('petugas');

        // Filter by search term
        if ($request->has('search')) {
            $searchTerm = $request->get('search');
            Log::info('Applying search filter', ['search_term' => $searchTerm]);
            $query->search($searchTerm);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
            Log::info('Applying status filter', ['status' => $request->get('status')]);
        }

        // Filter by petugas name
        if ($request->has('petugas')) {
            $petugasTerm = $request->get('petugas');
            $query->whereHas('petugas', function ($q) use ($petugasTerm) {
                $q->where('nama', 'like', "%{$petugasTerm}%");
            });
            Log::info('Applying petugas filter', ['petugas' => $petugasTerm]);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $mappings = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        Log::info('Query results', [
            'total_count' => $mappings->total(),
            'per_page' => $mappings->perPage(),
            'current_page' => $mappings->currentPage(),
            'first_item' => $mappings->firstItem(),
            'last_item' => $mappings->lastItem()
        ]);

        return response()->json([
            'success' => true,
            'data' => $mappings
        ]);
    }

    /**
     * Store a newly created performer role mapping.
     *
     * @bodyParam id_petugas string required Petugas ID. Example: 12345
     * @bodyParam code string required SNOMED CT code. Example: 123456
     * @bodyParam system string SNOMED system URI. Defaults to http://snomed.info/sct. Example: http://snomed.info/sct
     * @bodyParam display string required SNOMED display name. Example: Surgeon
     * @bodyParam description string Optional description. Example: Surgical specialist
     * @bodyParam status string Mapping status. Defaults to active. Example: active
     * @bodyParam notes string Optional notes. Example: For surgical procedures only
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        Log::info('Performer role mapping store request:', [
            'all_data' => $request->all()
        ]);

        // Manual validation
        $validated = $request->validate([
            'id_petugas' => 'required|string|max:20|exists:petugas,nip',
            'code' => 'required|string|max:20',
            'system' => 'required|string|max:50',
            'display' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive,draft',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['created_by'] = $request->user()?->name ?? 'system';
        $validated['updated_by'] = $validated['created_by'];

        $mapping = RsiaMappingPerformerRole::create($validated);
        $mapping->load('petugas');

        Log::info('Performer role mapping created successfully', [
            'mapping' => $mapping->toArray()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Performer role mapping created successfully',
            'data' => $mapping
        ], 201);
    }

    /**
     * Display the specified performer role mapping.
     *
     * @urlParam id_petugas string required Petugas ID. Example: 12345
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id_petugas)
    {
        Log::info('RsiaMappingPerformerRoleController::show called', [
            'id_petugas' => $id_petugas
        ]);

        $mapping = RsiaMappingPerformerRole::with('petugas')
                                            ->findOrFail($id_petugas);

        Log::info('Found performer mapping', ['mapping' => $mapping->toArray()]);

        return response()->json([
            'success' => true,
            'data' => $mapping
        ]);
    }

    /**
     * Update the specified performer role mapping.
     *
     * @urlParam id_petugas string required Petugas ID. Example: 12345
     * @bodyParam code string SNOMED CT code. Example: 123456
     * @bodyParam system string SNOMED system URI. Example: http://snomed.info/sct
     * @bodyParam display string SNOMED display name. Example: Surgeon
     * @bodyParam description string Optional description. Example: Surgical specialist
     * @bodyParam status string Mapping status. Example: active
     * @bodyParam notes string Optional notes. Example: Updated terminology
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id_petugas)
    {
        $mapping = RsiaMappingPerformerRole::findOrFail($id_petugas);

        $validated = $request->validate([
            'code' => 'required|string|max:20',
            'system' => 'required|string|max:50',
            'display' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive,draft',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['updated_by'] = $request->user()?->name ?? 'system';

        $mapping->update($validated);
        $mapping->load('petugas');

        Log::info('Performer role mapping updated successfully', [
            'mapping' => $mapping->toArray()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Performer role mapping updated successfully',
            'data' => $mapping
        ]);
    }

    /**
     * Remove the specified performer role mapping.
     *
     * @urlParam id_petugas string required Petugas ID. Example: 12345
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id_petugas)
    {
        $mapping = RsiaMappingPerformerRole::findOrFail($id_petugas);
        $mapping->delete();

        Log::info('Performer role mapping deleted successfully', [
            'id_petugas' => $id_petugas
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Performer role mapping deleted successfully'
        ]);
    }

    /**
     * Get petugas that don't have SNOMED mapping yet.
     *
     * @queryParam search string Search term for petugas name. Example: Dokter
     * @queryParam per_page int Items per page. Defaults to 15. Example: 10
     * @queryParam page int Page number. Defaults to 1. Example: 1
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function unmapped(Request $request)
    {
        $query = Petugas::whereNotIn('nip', function ($q) {
            $q->select('id_petugas')
              ->from('rsia_mapping_performer_role')
              ->whereNotNull('code')
              ->whereNotNull('display')
              ->where('status', 'active');
        });

        if ($request->has('search')) {
            $searchTerm = $request->get('search');
            $query->where('nama', 'like', "%{$searchTerm}%");
        }

        $perPage = $request->get('per_page', 15);
        $petugas = $query->select('nip', 'nama', 'jk')
                           ->orderBy('nama')
                           ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $petugas
        ]);
    }

    /**
     * Bulk update performer role mappings.
     *
     * @bodyParam mappings object required Key-value pairs of petugas IDs and their mappings. Example: {"12345": {"code": "123456", "display": "Surgeon"}}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'mappings' => 'required|array',
            'mappings.*.code' => 'nullable|string|max:20',
            'mappings.*.display' => 'nullable|string|max:255',
            'mappings.*.system' => 'required|string|max:50',
            'mappings.*.status' => 'required|in:active,inactive,draft',
        ]);

        $mappings = $request->get('mappings');
        $updatedBy = $request->user()?->name ?? 'system';

        DB::transaction(function () use ($mappings, $updatedBy) {
            foreach ($mappings as $idPetugas => $mappingData) {
                $mappingData['updated_by'] = $updatedBy;

                RsiaMappingPerformerRole::updateOrCreate(
                    ['id_petugas' => $idPetugas],
                    $mappingData
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Performer role mappings updated successfully',
            'count' => count($mappings)
        ]);
    }

    /**
     * Get statistics for performer role mappings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $totalPetugas = Petugas::count();
        $mappedPetugas = RsiaMappingPerformerRole::whereNotNull('code')
                                                ->whereNotNull('display')
                                                ->where('status', 'active')
                                                ->count();
        $unmappedPetugas = $totalPetugas - $mappedPetugas;

        $byStatus = RsiaMappingPerformerRole::selectRaw('status, COUNT(*) as count')
                                       ->groupBy('status')
                                       ->pluck('count', 'status')
                                       ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'total_petugas' => $totalPetugas,
                'mapped_petugas' => $mappedPetugas,
                'unmapped_petugas' => $unmappedPetugas,
                'mapping_percentage' => $totalPetugas > 0
                    ? round(($mappedPetugas / $totalPetugas) * 100, 2)
                    : 0,
                'by_status' => $byStatus
            ]
        ]);
    }
}