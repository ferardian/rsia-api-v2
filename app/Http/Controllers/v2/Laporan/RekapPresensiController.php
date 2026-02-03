<?php

namespace App\Http\Controllers\v2\Laporan;

use App\Models\RekapPresensi;
use App\Models\Pegawai;
use App\Models\Petugas;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\RealDataResource;

class RekapPresensiController extends Controller
{
    /**
     * Display a listing of the rekap presensi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $request->validate([
            'tgl_awal' => 'nullable|date_format:Y-m-d',
            'tgl_akhir' => 'nullable|date_format:Y-m-d',
            'shift' => 'nullable|string',
            'status' => 'nullable|string',
            'search' => 'nullable|string',
        ]);

        $tgl_awal = $request->tgl_awal ?? date('Y-m-d');
        $tgl_akhir = $request->tgl_akhir ?? date('Y-m-d');

        $query = RekapPresensi::with(['pegawai' => function ($q) {
            $q->select('id', 'nik', 'nama', 'jbtn', 'departemen');
        }])
        ->whereBetween('jam_datang', [$tgl_awal . ' 00:00:00', $tgl_akhir . ' 23:59:59']);

        if ($request->shift) {
            $query->where('shift', $request->shift);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $search = $request->search;
            $query->whereHas('pegawai', function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('jam_datang', 'desc')->paginate($request->limit ?? 50);

        return RealDataResource::collection($data);
    }

    /**
     * Get summary of status counts per employee including absent count from schedule
     */
    public function getSummary(Request $request)
    {
        $tgl_awal = $request->tgl_awal ?? date('Y-m-d');
        $tgl_akhir = $request->tgl_akhir ?? date('Y-m-d');

        // Subquery to aggregate attendance data
        $attendanceSub = \Illuminate\Support\Facades\DB::table('rekap_presensi')
            ->whereBetween('jam_datang', [$tgl_awal . ' 00:00:00', $tgl_akhir . ' 23:59:59'])
            ->select(
                'id',
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as total_hadir'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN shift LIKE "%Pagi%" THEN 1 ELSE 0 END) as pagi'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN shift LIKE "%Siang%" THEN 1 ELSE 0 END) as siang'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN shift LIKE "%Malam%" THEN 1 ELSE 0 END) as malam'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status = "Tepat Waktu" THEN 1 ELSE 0 END) as tepat_waktu'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status LIKE "Terlambat%" THEN 1 ELSE 0 END) as terlambat'),
                \Illuminate\Support\Facades\DB::raw('SUM(CASE WHEN status LIKE "%PSW%" THEN 1 ELSE 0 END) as psw')
            )
            ->groupBy('id');

        // Main query: All active staff (petugas.kd_jbtn != '-')
        $query = Pegawai::join('petugas', 'pegawai.nik', '=', 'petugas.nip')
            ->leftJoinSub($attendanceSub, 'att', function($join) {
                $join->on('pegawai.id', '=', 'att.id');
            })
            ->where('pegawai.stts_aktif', 'AKTIF')
            ->where('petugas.kd_jbtn', '!=', '-')
            ->select(
                'pegawai.id',
                'pegawai.nama',
                'pegawai.nik',
                'pegawai.jbtn',
                \Illuminate\Support\Facades\DB::raw('IFNULL(att.total_hadir, 0) as total_hadir'),
                \Illuminate\Support\Facades\DB::raw('IFNULL(att.pagi, 0) as pagi'),
                \Illuminate\Support\Facades\DB::raw('IFNULL(att.siang, 0) as siang'),
                \Illuminate\Support\Facades\DB::raw('IFNULL(att.malam, 0) as malam'),
                \Illuminate\Support\Facades\DB::raw('IFNULL(att.tepat_waktu, 0) as tepat_waktu'),
                \Illuminate\Support\Facades\DB::raw('IFNULL(att.terlambat, 0) as terlambat'),
                \Illuminate\Support\Facades\DB::raw('IFNULL(att.psw, 0) as psw')
            );

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('pegawai.nama', 'like', "%{$search}%")
                  ->orWhere('pegawai.nik', 'like', "%{$search}%");
            });
        }

        $summary = $query->orderBy('pegawai.nama', 'asc')->get();

        // --- Calculate "Tidak Presensi" (Absent) based on Jadwal ---
        $employeeIds = $summary->pluck('id');
        
        if ($employeeIds->isEmpty()) {
            return response()->json([
                'metaData' => ['code' => 200, 'message' => 'OK'],
                'response' => []
            ]);
        }

        $start = \Carbon\Carbon::parse($tgl_awal);
        $end = \Carbon\Carbon::parse($tgl_akhir);
        
        // Find distinct month-year periods in the range
        $periods = [];
        $tempPeriod = $start->copy()->startOfMonth();
        while ($tempPeriod <= $end) {
            $periods[] = [
                'bulan' => $tempPeriod->format('m'),
                'tahun' => $tempPeriod->format('Y')
            ];
            $tempPeriod->addMonth();
        }

        // Fetch schedules for those periods and employees
        $schedules = \Illuminate\Support\Facades\DB::table('jadwal_pegawai')
            ->whereIn('id', $employeeIds)
            ->where(function($q) use ($periods) {
                foreach ($periods as $p) {
                    $q->orWhere(function($sq) use ($p) {
                        $sq->where('bulan', $p['bulan'])->where('tahun', $p['tahun']);
                    });
                }
            })
            ->get()
            ->groupBy('id');

        // Fetch actual attendance dates per employee to compare
        $attendanceDates = \Illuminate\Support\Facades\DB::table('rekap_presensi')
            ->whereIn('id', $employeeIds)
            ->whereBetween('jam_datang', [$tgl_awal . ' 00:00:00', $tgl_akhir . ' 23:59:59'])
            ->select('id', \Illuminate\Support\Facades\DB::raw('DATE(jam_datang) as tgl'))
            ->get()
            ->groupBy('id')
            ->map(function($items) {
                return $items->pluck('tgl')->toArray();
            });

        // Calculate missing days for each employee
        foreach ($summary as $s) {
            $absentCount = 0;
            $empSchedules = $schedules->get($s->id, collect());
            $empAttDates = $attendanceDates->get($s->id, []);
            
            $currentDate = $start->copy();
            while ($currentDate <= $end) {
                $m = $currentDate->format('m');
                $y = $currentDate->format('Y');
                $d = ltrim($currentDate->format('d'), '0');
                $col = 'h' . $d;
                $dateStr = $currentDate->format('Y-m-d');
                
                $sched = $empSchedules->where('bulan', $m)->where('tahun', $y)->first();
                
                if ($sched && property_exists($sched, $col)) {
                    $shift = $sched->{$col};
                    // If scheduled (not empty, not '-', and not a common 'OFF' status)
                    if ($shift && $shift !== '-' && !in_array(strtoupper($shift), ['OFF', 'LIBUR', 'LC', 'CUTI'])) {
                        if (!in_array($dateStr, $empAttDates)) {
                            $absentCount++;
                        }
                    }
                }
                
                $currentDate->addDay();
            }
            $s->tidak_presensi = $absentCount;
        }

        return response()->json([
            'metaData' => ['code' => 200, 'message' => 'OK'],
            'response' => $summary
        ]);
    }
}
