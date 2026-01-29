<?php

namespace App\Http\Controllers\v2;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\RsiaCuti;
use Illuminate\Http\Request;

use App\Traits\LogsToTracker;

class CutiPegawaiController extends Controller
{
    use LogsToTracker;
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, string $nik)
    {
        $query = RsiaCuti::where('nik', $nik);
        
        // Filter by year if provided
        if ($request->has('year')) {
            $query->whereYear('tanggal_cuti', $request->year);
        }
        
        $cuti = $query->orderBy('tanggal_cuti', 'desc')->get();
        return new \App\Http\Resources\RealDataCollection($cuti);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(string $nik, Request $request)
    {
        $request->validate([
            // tanggal_cuti is object of start and end
            'tanggal_cuti' => 'required',
            'jenis'        => 'required|string|in:Cuti Tahunan,Cuti Bersalin,Cuti Diluar Tanggungan,Cuti Besar'
        ]);

        if (is_array($request->tanggal_cuti)) {
            $request->validate([
                'tanggal_cuti.start' => 'required|date',
                'tanggal_cuti.end'   => 'required|date'
            ]);
        }

        $pegawai = \App\Models\Pegawai::where('nik', $nik)->first();

        if (!$pegawai) {
            return ApiResponse::error('Pegawai tidak ditemukan', "resource_not_found", null, 404);
        }

        $data = [
            'id_pegawai'        => $pegawai->id,
            'nik'               => $nik,
            'nama'              => $pegawai->nama,
            'dep_id'            => $pegawai->departemen,
            'tanggal_cuti'      => $request->tanggal_cuti['start'],
            'id_jenis'          => $this->getIdJenisCuti($request->jenis),
            'jenis'             => $request->jenis,
            'status'            => 0,
            'tanggal_pengajuan' => \Carbon\Carbon::now()
        ];

        $dataCutiBersalin = [];
        if ($request->jenis == "Cuti Bersalin") {
            $request->validate([
                'tanggal_selesai' => 'required|date'
            ]);

            $dataCutiBersalin = [
                'tgl_mulai'   => $request->tanggal_cuti['start'],
                'tgl_selesai' => $request->tanggal_cuti['end'],
            ];
        }

        try {
            \DB::transaction(function () use ($data, $dataCutiBersalin, $request) {
                $cuti = RsiaCuti::create($data);

                if ($cuti->jenis == "Cuti Bersalin") {
                    $dataCutiBersalin['id_cuti'] = $cuti->id_cuti;

                    \App\Models\RsiaCutiBersalin::create($dataCutiBersalin);
                }

                $sql = "INSERT INTO rsia_cuti VALUES ('{$data['id_pegawai']}', '{$data['nik']}', '{$data['nama']}', '{$data['dep_id']}', '{$data['tanggal_cuti']}', '{$data['id_jenis']}', '{$data['jenis']}', '{$data['status']}', '{$data['tanggal_pengajuan']}')";
                $this->logTracker($sql, $request);
            }, 5);

            return ApiResponse::success('Cuti berhasil diajukan');
        } catch (\Throwable $th) {
            return ApiResponse::error('Cuti gagal diajukan', "internal_server_error", 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(string $nik, int $id)
    {
        $cuti = RsiaCuti::where('nik', $nik)->where('id_cuti', $id)->first();
        return new \App\Http\Resources\RealDataResource($cuti);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(string $nik, int $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(string $nik, int $id)
    {
        $cuti = RsiaCuti::where('nik', $nik)->where('id_cuti', $id)->first();

        if ($cuti->status == 0) {
            \DB::transaction(function () use ($cuti) {
                $cuti->delete();

                // check cuti bersalin if exists then delete
                $cutiBersalin = \App\Models\RsiaCutiBersalin::where('id_cuti', $cuti->id_cuti)->first();
                if ($cutiBersalin) {
                    $cutiBersalin->delete();
                }

                $sql = "DELETE FROM rsia_cuti WHERE id_cuti='{$cuti->id_cuti}'";
                $this->logTracker($sql, request());
            }, 5);
        } else {
            return ApiResponse::error('Cuti sudah disetujui, tidak bisa dihapus', "resource_not_found", 404);
        }

        return ApiResponse::success('Cuti berhasil dihapus');
    }

    /**
     * Counter cuti pegawai
     * 
     * @param \Illuminate\Http\Request $request
     * @param string $nik
     * @return \App\Http\Resources\RealDataResource
     * */
    public function counterCuti(Request $request, string $nik)
    {
        // Get year from request or use current year
        $year = $request->has('year') ? $request->year : date('Y');
        
        $hitung = \Illuminate\Support\Facades\DB::table('pegawai as t1')
            ->select(\Illuminate\Support\Facades\DB::raw("(SELECT count(id_pegawai) from rsia_cuti WHERE id_pegawai=t1.id and id_jenis = '1' and YEAR(tanggal_cuti)={$year} and MONTH(tanggal_cuti) < 07 and status_cuti='2' ) as jml1, (SELECT count(id_pegawai) from rsia_cuti WHERE id_pegawai=t1.id and id_jenis = '1' and MONTH(tanggal_cuti) > 06 and YEAR(tanggal_cuti)={$year} and MONTH(tanggal_cuti) <= 12 and status_cuti='2') as jml2"))
            ->where('t1.nik', $nik)
            ->get();

        $hitung = collect($hitung->first());

        return new \App\Http\Resources\RealDataResource($hitung);
    }

    /**
     * Get approval list - leave requests that current user can approve
     * 
     * @param \Illuminate\Http\Request $request
     * @param string $nik
     * @return \Illuminate\Http\Response
     * */
    public function getApprovalList(Request $request, string $nik)
    {
        // Get current user's department and job position
        $pegawai = \App\Models\Pegawai::where('nik', $nik)->first();
        
        if (!$pegawai) {
            return ApiResponse::error('Pegawai tidak ditemukan', "resource_not_found", null, 404);
        }

        // Debug: Log user info
        \Log::info('Approval Cuti - User Info', [
            'nik' => $nik,
            'departemen' => $pegawai->departemen,
            'jnj_jabatan' => $pegawai->jnj_jabatan
        ]);

        // Get subordinate departments from mapping
        // Based on user's department (dep_id_up) and job position (kd_jabatan_up)
        $mappings = \Illuminate\Support\Facades\DB::table('rsia_mapping_jabatan')
            ->where('dep_id_up', $pegawai->departemen)
            ->where('kd_jabatan_up', $pegawai->jnj_jabatan)
            ->get();

        // Debug: Log mapping results
        \Log::info('Approval Cuti - Mapping Results', [
            'count' => $mappings->count(),
            'mappings' => $mappings->toArray()
        ]);

        if ($mappings->isEmpty()) {
            // No subordinates, return empty list
            return new \App\Http\Resources\RealDataCollection(collect([]));
        }

        // Extract both dep_id_down AND kd_jabatan_down from mapping
        $subordinateDepts = $mappings->pluck('dep_id_down')->unique()->values()->toArray();
        $subordinatePositions = $mappings->pluck('kd_jabatan_down')->unique()->values()->toArray();

        // Debug: Log filter criteria
        \Log::info('Approval Cuti - Filter Criteria', [
            'subordinate_depts' => $subordinateDepts,
            'subordinate_positions' => $subordinatePositions
        ]);

        // Get leave requests from employees in subordinate departments AND positions
        // Only show employees whose position is in kd_jabatan_down (direct subordinates)
        $query = RsiaCuti::select('rsia_cuti.*')
            ->join('pegawai', 'rsia_cuti.id_pegawai', '=', 'pegawai.id')
            ->whereIn('rsia_cuti.dep_id', $subordinateDepts)
            ->whereIn('pegawai.jnj_jabatan', $subordinatePositions) // Only subordinate positions from mapping
            ->orderBy('rsia_cuti.tanggal_pengajuan', 'desc');

        // Filter by year if provided, otherwise default to current year
        if ($request->has('year')) {
            if ($request->year !== 'all' && $request->year !== 'Semua') {
                $query->whereYear('rsia_cuti.tanggal_cuti', $request->year);
            }
        } else {
             // Default: Tahun ini
             $query->whereYear('rsia_cuti.tanggal_cuti', date('Y'));
        }

        $cutiList = $query->get();
        
        // Debug: Log result count
        \Log::info('Approval Cuti - Results', [
            'count' => $cutiList->count()
        ]);
        
        return new \App\Http\Resources\RealDataCollection($cutiList);
    }

    /**
     * Approve leave request
     * 
     * @param \Illuminate\Http\Request $request
     * @param string $nik
     * @param int $id
     * @return \Illuminate\Http\Response
     * */
    public function approveLeave(Request $request, string $nik, int $id)
    {
        $cuti = RsiaCuti::where('id_cuti', $id)->first();

        if (!$cuti) {
            return ApiResponse::error('Data cuti tidak ditemukan', "resource_not_found", null, 404);
        }

        if ($cuti->status_cuti != '0') {
            return ApiResponse::error('Cuti sudah diproses sebelumnya', "invalid_status", null, 400);
        }

        // Verify user has permission to approve
        $pegawai = \App\Models\Pegawai::where('nik', $nik)->first();
        $subordinateDepts = \Illuminate\Support\Facades\DB::table('rsia_mapping_jabatan')
            ->where('dep_id_up', $pegawai->departemen)
            ->where('kd_jabatan_up', $pegawai->jnj_jabatan)
            ->pluck('dep_id_down')
            ->toArray();

        if (!in_array($cuti->dep_id, $subordinateDepts)) {
            return ApiResponse::error('Anda tidak memiliki akses untuk menyetujui cuti ini', "unauthorized", null, 403);
        }

        $cuti->status_cuti = '2'; // Approved
        $cuti->save();

        $sql = "UPDATE rsia_cuti SET status_cuti='2' WHERE id_cuti='{$id}'";
        $this->logTracker($sql, $request);

        return ApiResponse::success('Cuti berhasil disetujui');
    }

    /**
     * Reject leave request
     * 
     * @param \Illuminate\Http\Request $request
     * @param string $nik
     * @param int $id
     * @return \Illuminate\Http\Response
     * */
    public function rejectLeave(Request $request, string $nik, int $id)
    {
        $cuti = RsiaCuti::where('id_cuti', $id)->first();

        if (!$cuti) {
            return ApiResponse::error('Data cuti tidak ditemukan', "resource_not_found", null, 404);
        }

        if ($cuti->status_cuti != '0') {
            return ApiResponse::error('Cuti sudah diproses sebelumnya', "invalid_status", null, 400);
        }

        // Verify user has permission to reject
        $pegawai = \App\Models\Pegawai::where('nik', $nik)->first();
        $subordinateDepts = \Illuminate\Support\Facades\DB::table('rsia_mapping_jabatan')
            ->where('dep_id_up', $pegawai->departemen)
            ->where('kd_jabatan_up', $pegawai->jnj_jabatan)
            ->pluck('dep_id_down')
            ->toArray();

        if (!in_array($cuti->dep_id, $subordinateDepts)) {
            return ApiResponse::error('Anda tidak memiliki akses untuk menolak cuti ini', "unauthorized", null, 403);
        }

        $cuti->status_cuti = '3'; // Rejected
        $cuti->save();

        $sql = "UPDATE rsia_cuti SET status_cuti='3' WHERE id_cuti='{$id}'";
        $this->logTracker($sql, $request);

        return ApiResponse::success('Cuti berhasil ditolak');
    }

    /**
     * Get id jenis cuti
     * 
     * @param string $jenis
     * @return int
     * */
    private function getIdJenisCuti(string $jenis)
    {
        $jenisCuti = [
            'Cuti Tahunan'          => 1,
            'Cuti Bersalin'         => 2,
            'Cuti Diluar Tanggungan' => 3,
            'Cuti Besar'            => 4
        ];

        return $jenisCuti[$jenis];
    }
}
