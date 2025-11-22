<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\RsiaMappingLabRequest;
use App\Http\Resources\RsiaMappingLabResource;
use App\Models\RsiaMappingLab;
use Illuminate\Http\Request;

class RsiaMappingLabController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @queryParam page int Halaman yang ditampilkan. Defaults to 1. Example: 1
     * @queryParam per_page int Jumlah data per halaman. Defaults to 10. Example: 10
     * @queryParam search string Pencarian berdasarkan code, system, atau display. Example: loinc
     * @queryParam sort string Pengurutan berdasarkan kolom. Example: created_at
     * @queryParam order string Urutan ascending/descending. Defaults to asc. Example: desc
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $search = $request->query('search');
        $sort = $request->query('sort', 'kd_jenis_prw');
        $order = $request->query('order', 'asc');

        $query = RsiaMappingLab::with('jenisPerawatanLab');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('system', 'like', "%{$search}%")
                  ->orWhere('display', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sort, $order);

        $mappingLabs = $query->paginate($perPage);

        return RsiaMappingLabResource::collection($mappingLabs);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\RsiaMappingLabRequest  $request
     * @return \App\Http\Resources\RsiaMappingLabResource
     */
    public function store(RsiaMappingLabRequest $request)
    {
        try {
            $mappingLab = RsiaMappingLab::create($request->validated());

            // Load relationships
            $mappingLab->load('jenisPerawatanLab');

            return new RsiaMappingLabResource($mappingLab);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menyimpan data mapping lab', 'store_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \App\Http\Resources\RsiaMappingLabResource
     */
    public function show($id)
    {
        $mappingLab = RsiaMappingLab::with('jenisPerawatanLab')->find($id);

        if (!$mappingLab) {
            return ApiResponse::notFound('Data mapping lab tidak ditemukan');
        }

        return new RsiaMappingLabResource($mappingLab);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\RsiaMappingLabRequest  $request
     * @param  int  $id
     * @return \App\Http\Resources\RsiaMappingLabResource
     */
    public function update(RsiaMappingLabRequest $request, $id)
    {
        $mappingLab = RsiaMappingLab::find($id);

        if (!$mappingLab) {
            return ApiResponse::notFound('Data mapping lab tidak ditemukan');
        }

        try {
            $mappingLab->update($request->validated());

            // Load relationships
            $mappingLab->load('jenisPerawatanLab');

            return new RsiaMappingLabResource($mappingLab);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal mengupdate data mapping lab', 'update_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $mappingLab = RsiaMappingLab::find($id);

        if (!$mappingLab) {
            return ApiResponse::notFound('Data mapping lab tidak ditemukan');
        }

        try {
            $mappingLab->delete();
            return ApiResponse::success('Data mapping lab berhasil dihapus');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menghapus data mapping lab', 'delete_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Get mapping lab by jenis perawatan code.
     *
     * @param  string  $kdJenisPrw
     * @return \App\Http\Resources\RsiaMappingLabResource
     */
    public function getByJenisPerawatan($kdJenisPrw)
    {
        $mappingLab = RsiaMappingLab::with('jenisPerawatanLab')
            ->where('kd_jenis_prw', $kdJenisPrw)
            ->first();

        if (!$mappingLab) {
            return ApiResponse::notFound('Data mapping lab tidak ditemukan untuk jenis perawatan ini');
        }

        return new RsiaMappingLabResource($mappingLab);
    }

    /**
     * Bulk create mapping labs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'data' => 'required|array|min:1',
            'data.*.kd_jenis_prw' => 'required|exists:jns_perawatan_lab,kd_jenis_prw',
            'data.*.code' => 'nullable|string|max:15',
            'data.*.system' => 'required|string|max:100',
            'data.*.display' => 'nullable|string|max:80',
        ]);

        try {
            $created = RsiaMappingLab::insert($request->data);
            return ApiResponse::success('Data mapping lab berhasil dibuat secara bulk', ['created_count' => count($request->data)]);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menyimpan data mapping lab secara bulk', 'bulk_store_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Get mapping labs by system.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getBySystem(Request $request)
    {
        $request->validate([
            'system' => 'required|string|max:100',
        ]);

        $perPage = $request->query('per_page', 10);

        $mappingLabs = RsiaMappingLab::with('jenisPerawatanLab')
            ->where('system', $request->system)
            ->paginate($perPage);

        return RsiaMappingLabResource::collection($mappingLabs);
    }
}