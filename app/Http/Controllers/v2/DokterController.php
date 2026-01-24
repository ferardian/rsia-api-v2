<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Dokter;
use App\Models\Pegawai;
use App\Traits\LogsToTracker;

class DokterController extends Controller
{
    use LogsToTracker;

    /**
     * Display a listing of doctors
     */
    public function index(Request $request)
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 50);
        $search = $request->query('search', '');

        $query = Dokter::with(['spesialis', 'pegawai'])
            ->where('status', '1');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nm_dokter', 'LIKE', "%{$search}%")
                  ->orWhere('kd_dokter', 'LIKE', "%{$search}%")
                  ->orWhere('alumni', 'LIKE', "%{$search}%");
            });
        }

        $dokter = $query->orderBy('nm_dokter', 'asc')
                       ->paginate($limit, ['*'], 'page', $page);

        $transformedData = $dokter->items();
        foreach ($transformedData as &$item) {
            $item->spesialis_nama = $item->spesialis->nm_sps ?? '-';
            $item->pegawai_photo = $item->pegawai->photo ?? null;
        }

        return response()->json([
            'success' => true,
            'data' => $transformedData,
            'pagination' => [
                'current_page' => $dokter->currentPage(),
                'last_page' => $dokter->lastPage(),
                'per_page' => $dokter->perPage(),
                'total' => $dokter->total(),
                'from' => $dokter->firstItem(),
                'to' => $dokter->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created doctor
     */
    public function store(Request $request)
    {
        $request->validate([
            'kd_dokter' => 'required|string|unique:dokter,kd_dokter',
            'nm_dokter' => 'required|string',
            'jk' => 'required|in:L,P',
            'kd_sps' => 'nullable|string',
            'alumni' => 'nullable|string',
            'no_ijn_praktek' => 'nullable|string',
        ]);

        try {
            // Check if pegawai exists
            $pegawai = Pegawai::where('nik', $request->kd_dokter)->first();
            if (!$pegawai) {
                return \App\Helpers\ApiResponse::error('Pegawai not found', 'pegawai_not_found', null, 404);
            }

            $dokter = Dokter::create([
                'kd_dokter' => $request->kd_dokter,
                'nm_dokter' => $request->nm_dokter ?? $pegawai->nama,
                'jk' => $request->jk ?? ($pegawai->jk == 'Pria' ? 'L' : 'P'),
                'tmp_lahir' => $request->tmp_lahir ?? $pegawai->tmp_lahir,
                'tgl_lahir' => $request->tgl_lahir ?? $pegawai->tgl_lahir,
                'gol_drh' => $request->gol_drh ?? '-',
                'agama' => $request->agama ?? 'ISLAM',
                'almt_tgl' => $request->almt_tgl ?? $pegawai->alamat,
                'no_telp' => $request->no_telp ?? '-',
                'stts_nikah' => $request->stts_nikah ?? 'BELUM MENIKAH',
                'kd_sps' => $request->kd_sps,
                'alumni' => $request->alumni,
                'no_ijn_praktek' => $request->no_ijn_praktek,
                'status' => '1'
            ]);

            $this->logTracker("INSERT dokter for kd_dokter: {$request->kd_dokter}", $request);

            return \App\Helpers\ApiResponse::success('Doctor created successfully', $dokter);
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to create doctor', 'create_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified doctor
     */
    public function show($id)
    {
        $dokter = Dokter::with(['spesialis', 'pegawai'])->find($id);

        if (!$dokter) {
            return \App\Helpers\ApiResponse::notFound('Doctor not found');
        }

        return response()->json([
            'success' => true,
            'data' => $dokter
        ]);
    }

    /**
     * Update the specified doctor
     */
    public function update(Request $request, $id)
    {
        $dokter = Dokter::find($id);

        if (!$dokter) {
            return \App\Helpers\ApiResponse::notFound('Doctor not found');
        }

        $request->validate([
            'nm_dokter' => 'sometimes|string',
            'jk' => 'sometimes|in:L,P',
            'kd_sps' => 'nullable|string',
            'alumni' => 'nullable|string',
            'no_ijn_praktek' => 'nullable|string',
        ]);

        try {
            $dokter->update($request->all());

            $this->logTracker("UPDATE dokter for kd_dokter: {$id}", $request);

            return \App\Helpers\ApiResponse::success('Doctor updated successfully', $dokter);
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to update doctor', 'update_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified doctor
     */
    public function destroy($id)
    {
        $dokter = Dokter::find($id);

        if (!$dokter) {
            return \App\Helpers\ApiResponse::notFound('Doctor not found');
        }

        try {
            // Soft delete by setting status to 0
            $dokter->update(['status' => '0']);

            $this->logTracker("SOFT DELETE dokter for kd_dokter: {$id}", request());

            return \App\Helpers\ApiResponse::success('Doctor deleted successfully');
        } catch (\Exception $e) {
            return \App\Helpers\ApiResponse::error('Failed to delete doctor', 'delete_failed', $e->getMessage(), 500);
        }
    }

    /**
     * Search doctors
     */
    public function search(Request $request)
    {
        $query = $request->query('q', '');
        $limit = $request->query('limit', 20);

        $dokter = Dokter::with(['spesialis'])
            ->where('status', '1')
            ->where(function($q) use ($query) {
                $q->where('nm_dokter', 'LIKE', "%{$query}%")
                  ->orWhere('kd_dokter', 'LIKE', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $dokter
        ]);
    }

    /**
     * Get list of spesialisasi for dropdown
     */
    public function getSpesialisasi()
    {
        $spesialis = \DB::table('spesialis')
            ->select('kd_sps', 'nm_sps')
            ->orderBy('nm_sps', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $spesialis
        ]);
    }
}
