<?php

namespace App\Http\Controllers\v2;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\RsiaCuti;
use Illuminate\Http\Request;

use App\Traits\LogsToTracker;
use App\Jobs\SendWhatsApp;
use App\Models\RsiaMappingJabatan;
use App\Models\Pegawai;
use App\Models\Petugas;

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
            'tanggal_cuti' => 'required_without:dates',
            'dates'        => 'required_without:tanggal_cuti|array',
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

        $dates = [];
        if ($request->has('dates') && is_array($request->dates)) {
            $dates = $request->dates;
        } else {
            // Fallback for range - expand to individual dates if not Cuti Bersalin
            if ($request->jenis == "Cuti Bersalin") {
                $dates = [$request->tanggal_cuti['start']];
            } else {
                $start = \Carbon\Carbon::parse($request->tanggal_cuti['start']);
                $end = \Carbon\Carbon::parse($request->tanggal_cuti['end']);
                while ($start->lte($end)) {
                    $dates[] = $start->copy()->format('Y-m-d');
                    $start->addDay();
                }
            }
        }

        try {
            \DB::transaction(function () use ($nik, $pegawai, $dates, $request) {
                foreach ($dates as $date) {
                    $data = [
                        'id_pegawai'        => $pegawai->id,
                        'nik'               => $nik,
                        'nama'              => $pegawai->nama,
                        'dep_id'            => $pegawai->departemen,
                        'tanggal_cuti'      => $date,
                        'id_jenis'          => $this->getIdJenisCuti($request->jenis),
                        'jenis'             => $request->jenis,
                        'status'            => 0,
                        'tanggal_pengajuan' => \Carbon\Carbon::now()
                    ];

                    $cuti = RsiaCuti::create($data);

                    if ($request->jenis == "Cuti Bersalin") {
                        \App\Models\RsiaCutiBersalin::create([
                            'id_cuti' => $cuti->id_cuti,
                            'tgl_mulai' => $request->tanggal_cuti['start'],
                            'tgl_selesai' => $request->tanggal_cuti['end'],
                        ]);
                    }

                    $sql = "INSERT INTO rsia_cuti VALUES ('{$data['id_pegawai']}', '{$data['nik']}', '{$data['nama']}', '{$data['dep_id']}', '{$data['tanggal_cuti']}', '{$data['id_jenis']}', '{$data['jenis']}', '{$data['status']}', '{$data['tanggal_pengajuan']}')";
                    $this->logTracker($sql, $request);
                }
            }, 5);

            // Send WA Notification to superiors (Consolidated)
            $this->sendWhatsAppNotification($nik, [
                'nama' => $pegawai->nama,
                'jenis' => $request->jenis,
                'dates' => $dates,
                'is_range' => $request->jenis == "Cuti Bersalin",
                'range' => [
                    'start' => $request->tanggal_cuti['start'] ?? null,
                    'end' => $request->tanggal_cuti['end'] ?? null
                ]
            ]);

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

        // Only allow coordinators (status_koor = 1) or specific high-level positions to approve
        if ($pegawai->status_koor == '0' && !in_array($pegawai->jnj_jabatan, ['RS1', 'RS2', 'RS3', 'RS4', 'RS5'])) {
            return new \App\Http\Resources\RealDataCollection(collect([]));
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

        // Send notification to employee
        $this->sendStatusNotification($id, '2');

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

        // Send notification to employee
        $this->sendStatusNotification($id, '3');

        return ApiResponse::success('Cuti berhasil ditolak');
    }

    /**
     * Get summary of leave usage per semester for all active staff
     */
    public function rekapCuti(Request $request)
    {
        $year = $request->year ?? date('Y');
        $id_jenis = $request->id_jenis ?? 1; // Default: Cuti Tahunan

        if ($id_jenis == 1) {
            // Summary mode for Cuti Tahunan
            $cutiSub = \Illuminate\Support\Facades\DB::table('rsia_cuti')
                ->whereYear('tanggal_cuti', $year)
                ->where('id_jenis', '1')
                ->where('status_cuti', '2')
                ->select(
                    'id_pegawai',
                    \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN MONTH(tanggal_cuti) <= 6 THEN 1 ELSE 0 END) as s1_took'),
                    \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN MONTH(tanggal_cuti) > 6 THEN 1 ELSE 0 END) as s2_took')
                )
                ->groupBy('id_pegawai');

            $query = \App\Models\Pegawai::join('petugas', 'pegawai.nik', '=', 'petugas.nip')
                ->leftJoin('departemen', 'pegawai.departemen', '=', 'departemen.dep_id')
                ->leftJoinSub($cutiSub, 'cuti', function($join) {
                    $join->on('pegawai.id', '=', 'cuti.id_pegawai');
                })
                ->where('pegawai.stts_aktif', 'AKTIF')
                ->where('petugas.kd_jbtn', '!=', '-')
                ->select(
                    'pegawai.id',
                    'pegawai.nama',
                    'pegawai.nik',
                    'pegawai.jbtn',
                    'pegawai.departemen as dep_id',
                    'departemen.nama as departemen',
                    \Illuminate\Support\Facades\DB::raw('IFNULL(cuti.s1_took, 0) as s1_took'),
                    \Illuminate\Support\Facades\DB::raw('IFNULL(cuti.s2_took, 0) as s2_took'),
                    \Illuminate\Support\Facades\DB::raw('6 - IFNULL(cuti.s1_took, 0) as s1_left'),
                    \Illuminate\Support\Facades\DB::raw('6 - IFNULL(cuti.s2_took, 0) as s2_left')
                );
        } else {
            // List mode for other leave types
            $query = \App\Models\RsiaCuti::join('pegawai', 'rsia_cuti.id_pegawai', '=', 'pegawai.id')
                ->leftJoin('departemen', 'pegawai.departemen', '=', 'departemen.dep_id')
                ->where('rsia_cuti.id_jenis', $id_jenis)
                ->whereYear('rsia_cuti.tanggal_cuti', $year)
                ->where('rsia_cuti.status_cuti', '2')
                ->select(
                    'pegawai.nama',
                    'pegawai.nik',
                    'pegawai.departemen as dep_id',
                    'departemen.nama as departemen',
                    'rsia_cuti.tanggal_cuti',
                    'rsia_cuti.id_cuti'
                );

            if ($id_jenis == 2) {
                // Join with cuti_bersalin to get range
                $query->leftJoin('rsia_cuti_bersalin', 'rsia_cuti.id_cuti', '=', 'rsia_cuti_bersalin.id_cuti')
                    ->addSelect('rsia_cuti_bersalin.tgl_mulai', 'rsia_cuti_bersalin.tgl_selesai');
            }
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('pegawai.nama', 'like', "%{$search}%")
                  ->orWhere('pegawai.nik', 'like', "%{$search}%");
            });
        }

        if ($request->dep_id) {
            $query->where('pegawai.departemen', $request->dep_id);
        }

        $rekap = $query->orderBy('pegawai.nama', 'asc')->get();

        return response()->json([
            'metaData' => ['code' => 200, 'message' => 'OK'],
            'response' => $rekap
        ]);
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

    /**
     * Send WhatsApp notification to superiors
     * 
     * @param string $nik
     * @param array $data
     * @return void
     * */
    private function sendWhatsAppNotification(string $nik, array $data)
    {
        $pegawai = Pegawai::with('dep')->where('nik', $nik)->first();
        if (!$pegawai) return;

        // Get mappings for superiors
        $mappings = RsiaMappingJabatan::where('dep_id_down', $pegawai->departemen)
            ->where('kd_jabatan_down', $pegawai->jnj_jabatan)
            ->get();

        if ($mappings->isEmpty()) return;

        // Date formatting logic
        if ($data['is_range']) {
            $tglMulai = \Carbon\Carbon::parse($data['range']['start'])->translatedFormat('d F Y');
            $tglSelesai = \Carbon\Carbon::parse($data['range']['end'])->translatedFormat('d F Y');
            $tglPengajuan = ($tglMulai == $tglSelesai) ? $tglMulai : "{$tglMulai} s/d {$tglSelesai}";
        } else {
            // Handle multiple individual dates
            $formattedDates = array_map(function($d) {
                return \Carbon\Carbon::parse($d)->translatedFormat('d F Y');
            }, $data['dates']);
            
            if (count($formattedDates) === 1) {
                $tglPengajuan = $formattedDates[0];
            } else {
                $tglPengajuan = "\n- " . implode("\n- ", $formattedDates);
            }
        }

        // Message template
        $message = "*Notifikasi Pengajuan Cuti*\n\n";
        $message .= "Nama : *{$data['nama']}*\n";
        $message .= "NIK : {$nik}\n";
        $message .= "Unit : {$pegawai->dep->nama}\n";
        $message .= "Tanggal : {$tglPengajuan}\n";
        $message .= "Jenis : {$data['jenis']}\n\n";
        $message .= "Mohon untuk segera ditinjau dan dilakukan persetujuan pada aplikasi RSIAP v2.\n\n";
        $message .= "*RSIA AISYIYAH PEKAJANGAN*";

        foreach ($mappings as $map) {
            // Find superiors in this department and position, ensuring they are active coordinators
            $superiors = Pegawai::where('departemen', $map->dep_id_up)
                ->where('jnj_jabatan', $map->kd_jabatan_up)
                ->where('stts_aktif', 'AKTIF')
                ->where(function ($query) {
                    // For high level positions, status_koor might not be set, but for mid-management it should be 1
                    $query->where('status_koor', '1')
                          ->orWhereIn('jnj_jabatan', ['RS1', 'RS2', 'RS3', 'RS4', 'RS5']);
                })
                ->get();

            foreach ($superiors as $sup) {
                $petugas = Petugas::where('nip', $sup->nik)->first();
                if ($petugas && $petugas->no_telp) {
                    SendWhatsApp::dispatchAfterResponse($petugas->no_telp, $message);
                }
            }
        }
    }

    /**
     * Send status notification to employee (Approved/Rejected)
     * 
     * @param int $id_cuti
     * @param string $status
     * @return void
     * */
    private function sendStatusNotification(int $id_cuti, string $status)
    {
        $cuti = RsiaCuti::where('id_cuti', $id_cuti)->first();
        if (!$cuti) return;

        $pegawai = Pegawai::where('nik', $cuti->nik)->first();
        if (!$pegawai) return;

        $petugas = Petugas::where('nip', $pegawai->nik)->first();
        if (!$petugas || !$petugas->no_telp) return;

        $statusLabel = ($status == '2') ? '*DISETUJUI*' : '*DITOLAK*';
        $tanggal = \Carbon\Carbon::parse($cuti->tanggal_cuti)->translatedFormat('d F Y');

        $message = "*Notifikasi Status Pengajuan Cuti*\n\n";
        $message .= "Halo, *{$pegawai->nama}*\n";
        $message .= "Pengajuan cuti Anda untuk tanggal *{$tanggal}* telah {$statusLabel}.\n\n";
        
        if ($status == '2') {
            $message .= "Selamat beristirahat!\n\n";
        } else {
            $message .= "Mohon maaf, pengajuan Anda belum dapat disetujui saat ini.\n\n";
        }

        $message .= "*RSIA AISYIYAH PEKAJANGAN*";

        SendWhatsApp::dispatchAfterResponse($petugas->no_telp, $message);
    }
}
