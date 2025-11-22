<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\RsiaMappingProcedureRequest;
use App\Http\Resources\RsiaMappingProcedureResource;
use App\Models\RsiaMappingProcedure;
use App\Models\JenisPerawatan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RsiaMappingProcedureController extends Controller
{
    /**
     * Display a listing of procedure mappings.
     *
     * @queryParam search string Search term for SNOMED code or display name. Example: 123456
     * @queryParam status string Filter by status (active, inactive, draft). Example: active
     * @queryParam procedure string Search by procedure name. Example: Konsultasi
     * @queryParam per_page int Items per page. Defaults to 15. Example: 10
     * @queryParam page int Page number. Defaults to 1. Example: 1
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $query = RsiaMappingProcedure::with('jenisPerawatan');

        // Filter by search term
        if ($request->has('search')) {
            $searchTerm = $request->get('search');
            $query->search($searchTerm);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by procedure name
        if ($request->has('procedure')) {
            $query->byProcedureName($request->get('procedure'));
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $mappings = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        return RsiaMappingProcedureResource::collection($mappings);
    }

    /**
     * Store a newly created procedure mapping.
     *
     * @bodyParam kd_jenis_prw string required Procedure code from jns_perawatan. Example: RJ00125
     * @bodyParam code string required SNOMED CT code. Example: 123456
     * @bodyParam system string SNOMED system URI. Defaults to http://snomed.info/sct. Example: http://snomed.info/sct
     * @bodyParam display string required SNOMED display name. Example: Appendectomy
     * @bodyParam description string Optional description. Example: Surgical removal of appendix
     * @bodyParam status string Mapping status. Defaults to active. Example: active
     * @bodyParam notes string Optional notes. Example: Emergency procedure only
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource
     */
    public function store(RsiaMappingProcedureRequest $request)
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()?->name ?? 'system';
        $validated['updated_by'] = $validated['created_by'];

        $mapping = RsiaMappingProcedure::create($validated);
        $mapping->load('jenisPerawatan');

        return new RsiaMappingProcedureResource($mapping);
    }

    /**
     * Display the specified procedure mapping.
     *
     * @urlParam kd_jenis_prw string required Procedure code. Example: RJ00125
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource
     */
    public function show(string $kd_jenis_prw)
    {
        $mapping = RsiaMappingProcedure::with('jenisPerawatan')
                                    ->findOrFail($kd_jenis_prw);

        return new RsiaMappingProcedureResource($mapping);
    }

    /**
     * Update the specified procedure mapping.
     *
     * @urlParam kd_jenis_prw string required Procedure code. Example: RJ00125
     * @bodyParam code string SNOMED CT code. Example: 123456
     * @bodyParam system string SNOMED system URI. Example: http://snomed.info/sct
     * @bodyParam display string SNOMED display name. Example: Appendectomy
     * @bodyParam description string Optional description. Example: Surgical removal of appendix
     * @bodyParam status string Mapping status. Example: active
     * @bodyParam notes string Optional notes. Example: Updated terminology
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource
     */
    public function update(RsiaMappingProcedureRequest $request, string $kd_jenis_prw)
    {
        $mapping = RsiaMappingProcedure::findOrFail($kd_jenis_prw);

        $validated = $request->validated();
        $validated['updated_by'] = $request->user()?->name ?? 'system';

        $mapping->update($validated);
        $mapping->load('jenisPerawatan');

        return new RsiaMappingProcedureResource($mapping);
    }

    /**
     * Remove the specified procedure mapping.
     *
     * @urlParam kd_jenis_prw string required Procedure code. Example: RJ00125
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $kd_jenis_prw)
    {
        $mapping = RsiaMappingProcedure::findOrFail($kd_jenis_prw);
        $mapping->delete();

        return response()->json([
            'success' => true,
            'message' => 'Procedure mapping deleted successfully'
        ]);
    }

    /**
     * Get procedures that don't have SNOMED mapping yet.
     *
     * @queryParam search string Search term for procedure name. Example: Konsultasi
     * @queryParam per_page int Items per page. Defaults to 15. Example: 10
     * @queryParam page int Page number. Defaults to 1. Example: 1
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function unmapped(Request $request)
    {
        $query = JenisPerawatan::whereNotIn('kd_jenis_prw', function ($q) {
            $q->select('kd_jenis_prw')
              ->from('rsia_mapping_procedure')
              ->whereNotNull('code')
              ->whereNotNull('display')
              ->where('status', 'active');
        });

        if ($request->has('search')) {
            $searchTerm = $request->get('search');
            $query->where('nm_perawatan', 'like', "%{$searchTerm}%");
        }

        $perPage = $request->get('per_page', 15);
        $procedures = $query->select('kd_jenis_prw', 'nm_perawatan', 'kd_poli', 'kd_pj')
                           ->orderBy('nm_perawatan')
                           ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $procedures
        ]);
    }

    /**
     * Get procedure mapping by SNOMED code.
     *
     * @queryParam code string required SNOMED CT code. Example: 123456
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySnomedCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:20'
        ]);

        $mappings = RsiaMappingProcedure::with('jenisPerawatan')
                                       ->where('code', $request->get('code'))
                                       ->active()
                                       ->get();

        return RsiaMappingProcedureResource::collection($mappings);
    }

    /**
     * Bulk update procedure mappings.
     *
     * @bodyParam mappings object required Key-value pairs of procedure codes and their mappings. Example: {"RJ00125": {"code": "123456", "display": "Procedure name"}}
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

        RsiaMappingProcedure::bulkUpdate($mappings, $updatedBy);

        return response()->json([
            'success' => true,
            'message' => 'Procedure mappings updated successfully',
            'count' => count($mappings)
        ]);
    }

    /**
     * Get statistics for procedure mappings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $totalProcedures = JenisPerawatan::count();
        $mappedProcedures = RsiaMappingProcedure::active()
                                                ->whereNotNull('code')
                                                ->whereNotNull('display')
                                                ->count();
        $unmappedProcedures = $totalProcedures - $mappedProcedures;

        $byStatus = RsiaMappingProcedure::selectRaw('status, COUNT(*) as count')
                                       ->groupBy('status')
                                       ->pluck('count', 'status')
                                       ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'total_procedures' => $totalProcedures,
                'mapped_procedures' => $mappedProcedures,
                'unmapped_procedures' => $unmappedProcedures,
                'mapping_percentage' => $totalProcedures > 0
                    ? round(($mappedProcedures / $totalProcedures) * 100, 2)
                    : 0,
                'by_status' => $byStatus
            ]
        ]);
    }
}
