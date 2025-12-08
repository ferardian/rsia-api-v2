<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProcedureBillingResource;
use App\Models\ProcedureBilling;
use Illuminate\Http\Request;

class ProcedureBillingController extends Controller
{
    /**
     * Display a listing of procedure billing for a specific visit.
     *
     * @queryParam no_rawat required Rawat visit number. Example: 2024/01/01/0001
     * @queryParam sort string Pengurutan berdasarkan kolom. Example: tgl_perawatan
     * @queryParam order string Urutan ascending/descending. Defaults to desc. Example: desc
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $request->validate([
            'no_rawat' => 'required|string|exists:reg_periksa,no_rawat'
        ]);

        $sort = $request->query('sort', 'tgl_perawatan');
        $order = $request->query('order', 'desc');

        $query = ProcedureBilling::getProcedureBillingData($request->no_rawat)
            ->orderBy($sort, $order);

        $procedures = $query->get();

        return ProcedureBillingResource::collection($procedures);
    }

    /**
     * Get summary statistics for procedure billing.
     *
     * @queryParam no_rawat required Rawat visit number. Example: 2024/01/01/0001
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request)
    {
        $request->validate([
            'no_rawat' => 'required|string|exists:reg_periksa,no_rawat'
        ]);

        $query = ProcedureBilling::getProcedureBillingData($request->no_rawat);

        $totalProcedures = $query->count();
        $totalBiaya = $query->sum('biaya_rawat');

        // Group by jenis petugas
        $byJenisPetugas = $query->selectRaw('jenis_petugas, COUNT(*) as count, SUM(CAST(biaya_rawat AS DECIMAL(15,2))) as total')
            ->groupBy('jenis_petugas')
            ->get();

        // Group by status rawat
        $byStatusRawat = $query->selectRaw('status_rawat, COUNT(*) as count, SUM(CAST(biaya_rawat AS DECIMAL(15,2))) as total')
            ->groupBy('status_rawat')
            ->get();

        // Group by payment status
        $byStatusBayar = $query->selectRaw('stts_bayar, COUNT(*) as count, SUM(CAST(biaya_rawat AS DECIMAL(15,2))) as total')
            ->groupBy('stts_bayar')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_procedures' => $totalProcedures,
                'total_biaya' => $totalBiaya,
                'by_jenis_petugas' => $byJenisPetugas,
                'by_status_rawat' => $byStatusRawat,
                'by_status_bayar' => $byStatusBayar
            ]
        ]);
    }

    /**
     * Get top procedures by cost.
     *
     * @queryParam no_rawat required Rawat visit number. Example: 2024/01/01/0001
     * @queryParam limit int Jumlah data yang ditampilkan. Defaults to 5. Example: 5
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function topProcedures(Request $request)
    {
        $request->validate([
            'no_rawat' => 'required|string|exists:reg_periksa,no_rawat',
            'limit' => 'integer|min:1|max:20'
        ]);

        $limit = $request->query('limit', 5);

        // Alternative approach: Get all data first, then group manually
        $allProcedures = ProcedureBilling::getProcedureBillingData($request->no_rawat)
            ->select('nm_perawatan', 'biaya_rawat')
            ->get();

        // Group and aggregate manually
        $groupedProcedures = $allProcedures->groupBy('nm_perawatan')
            ->map(function ($group) {
                return [
                    'nm_perawatan' => $group->first()->nm_perawatan,
                    'frequency' => $group->count(),
                    'total_biaya' => $group->sum('biaya_rawat')
                ];
            })
            ->sortByDesc('total_biaya')
            ->take($limit)
            ->values();

        $topProcedures = $groupedProcedures;

        return response()->json([
            'success' => true,
            'data' => $topProcedures
        ]);
    }
}