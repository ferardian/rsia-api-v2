<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\RsiaMappingProcedureRequest;
use App\Http\Requests\RsiaMappingProcedureInapRequest;
use App\Http\Resources\RsiaMappingProcedureResource;
use App\Http\Resources\RsiaMappingProcedureInapResource;
use App\Models\RsiaMappingProcedure;
use App\Models\RsiaMappingProcedureInap;
use App\Models\JenisPerawatan;
use App\Models\JenisPerawatanInap;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RsiaMappingProcedureController extends Controller
{
    /**
     * Display a listing of procedure mappings.
     *
     * @queryParam search string Search term for SNOMED code or display name. Example: 123456
     * @queryParam status string Filter by status (active, inactive, draft). Example: active
     * @queryParam procedure string Search by procedure name. Example: Konsultasi
     * @queryParam type string Filter by procedure type (inap for inpatient, ralan for outpatient). Example: inap
     * @queryParam per_page int Items per page. Defaults to 15. Example: 10
     * @queryParam page int Page number. Defaults to 1. Example: 1
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        // Log incoming request details
        \Log::info('RsiaMappingProcedureController::index called', [
            'request_all' => $request->all(),
            'type' => $request->get('type'),
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'procedure' => $request->get('procedure')
        ]);

        // Handle inpatient vs outpatient procedure mappings
        $type = $request->get('type');

        if ($type === 'inap') {
            \Log::info('Using inpatient procedure mappings query');
            $query = RsiaMappingProcedureInap::with('jenisPerawatanInap');
        } else {
            \Log::info('Using outpatient procedure mappings query');
            $query = RsiaMappingProcedure::with('jenisPerawatan');
        }

        // Filter by search term
        if ($request->has('search')) {
            $searchTerm = $request->get('search');
            \Log::info('Applying search filter', ['search_term' => $searchTerm]);
            $query->search($searchTerm);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
            \Log::info('Applying status filter', ['status' => $request->get('status')]);
        }

        // Filter by procedure name
        if ($request->has('procedure')) {
            $query->byProcedureName($request->get('procedure'));
            \Log::info('Applying procedure name filter', ['procedure' => $request->get('procedure')]);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $mappings = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        \Log::info('Query results', [
            'total_count' => $mappings->total(),
            'per_page' => $mappings->perPage(),
            'current_page' => $mappings->currentPage(),
            'first_item' => $mappings->firstItem(),
            'last_item' => $mappings->lastItem()
        ]);

        if ($type === 'inap') {
            \Log::info('Returning inpatient procedure mapping resource collection');
            return RsiaMappingProcedureInapResource::collection($mappings);
        } else {
            \Log::info('Returning outpatient procedure mapping resource collection');
            return RsiaMappingProcedureResource::collection($mappings);
        }
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
    public function store(Request $request)
    {
        \Log::info('Procedure mapping store request:', [
            'all_data' => $request->all()
        ]);

        // Manual validation for both types
        $validated = $request->validate([
            'kd_jenis_prw' => 'required|string|max:15',
            'code' => 'required_without:display|string|max:20',
            'system' => 'required|string|max:50',
            'display' => 'required_without:code|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive,draft',
            'notes' => 'nullable|string|max:500',
        ]);

        // Determine if this is rawat inap or rawat jalan
        $kdJenisPrw = $validated['kd_jenis_prw'];
        $isRanap = DB::table('jns_perawatan_inap')
                       ->where('kd_jenis_prw', $kdJenisPrw)
                       ->exists();

        $validated['created_by'] = $request->user()?->name ?? 'system';
        $validated['updated_by'] = $validated['created_by'];

        if ($isRanap) {
            // Use inap mapping
            $mapping = RsiaMappingProcedureInap::create($validated);
            $mapping->load('jenisPerawatanInap');

            return new RsiaMappingProcedureInapResource($mapping);
        } else {
            // Use ralan mapping
            $mapping = RsiaMappingProcedure::create($validated);
            $mapping->load('jenisPerawatan');

            return new RsiaMappingProcedureResource($mapping);
        }
    }

    /**
     * Display the specified procedure mapping.
     *
     * @urlParam kd_jenis_prw string required Procedure code. Example: RJ00125
     * @queryParam type string Filter by procedure type (inap for inpatient, ralan for outpatient). Example: inap
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource
     */
    public function show(Request $request, string $kd_jenis_prw)
    {
        \Log::info('RsiaMappingProcedureController::show called', [
            'kd_jenis_prw' => $kd_jenis_prw,
            'type' => $request->get('type'),
            'request_all' => $request->all()
        ]);

        $type = $request->get('type');

        if ($type === 'inap') {
            \Log::info('Searching for inpatient procedure mapping', ['kd_jenis_prw' => $kd_jenis_prw]);
            $mapping = RsiaMappingProcedureInap::with('jenisPerawatanInap')
                                                ->findOrFail($kd_jenis_prw);
            \Log::info('Found inpatient mapping', ['mapping' => $mapping->toArray()]);
            return new RsiaMappingProcedureInapResource($mapping);
        } else {
            \Log::info('Searching for outpatient procedure mapping', ['kd_jenis_prw' => $kd_jenis_prw]);
            $mapping = RsiaMappingProcedure::with('jenisPerawatan')
                                            ->findOrFail($kd_jenis_prw);
            \Log::info('Found outpatient mapping', ['mapping' => $mapping->toArray()]);
            return new RsiaMappingProcedureResource($mapping);
        }
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
     * @queryParam type string Filter by procedure type (inap for inpatient, ralan for outpatient). Example: inap
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySnomedCode(Request $request)
    {
        \Log::info('RsiaMappingProcedureController::getBySnomedCode called', [
            'request_all' => $request->all(),
            'code' => $request->get('code'),
            'type' => $request->get('type')
        ]);

        $request->validate([
            'code' => 'required|string|max:20'
        ]);

        $type = $request->get('type');

        if ($type === 'inap') {
            \Log::info('Searching inpatient mappings by SNOMED code', ['code' => $request->get('code')]);
            $mappings = RsiaMappingProcedureInap::with('jenisPerawatanInap')
                                               ->where('code', $request->get('code'))
                                               ->active()
                                               ->get();
            \Log::info('Found inpatient mappings', ['count' => $mappings->count()]);
            return RsiaMappingProcedureInapResource::collection($mappings);
        } else {
            \Log::info('Searching outpatient mappings by SNOMED code', ['code' => $request->get('code')]);
            $mappings = RsiaMappingProcedure::with('jenisPerawatan')
                                           ->where('code', $request->get('code'))
                                           ->active()
                                           ->get();
            \Log::info('Found outpatient mappings', ['count' => $mappings->count()]);
            return RsiaMappingProcedureResource::collection($mappings);
        }
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
