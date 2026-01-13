<?php

namespace App\Http\Controllers\v2;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pegawai;
use App\Models\JadwalPegawai;     // Final Table
use App\Models\RsiaJadwalPegawai; // Draft/Submission Table
use App\Models\JamMasuk;
use Illuminate\Support\Facades\DB;

class JadwalPegawaiController extends Controller
{
    // FETCH SUBMISSION/DRAFT SCHEDULE
    public function index(Request $request) 
    {
        $bulanRaw = $request->input('bulan', date('m'));
        $bulan = sprintf('%02d', $bulanRaw); // Force 2 digits (e.g. 1 -> 01)
        
        $tahun = $request->input('tahun', date('Y'));
        $departemen = $request->input('departemen');
        $search = $request->input('search');

        // Query Pegawai
        $query = Pegawai::query()
            ->select('id', 'nik', 'nama', 'departemen', 'jbtn')
            ->with('dep:dep_id,nama') // Eager load department name
            ->where('stts_aktif', 'AKTIF');

        // Hierarchical Approval Logic
        // Filter employees based on who the current user can approve (Department based only)
        $user = auth()->user();
        $isSuperUser = false;
        
        // Check role if available (JWT claim)
        try {
            // Check explicit mode from request (e.g. from Admin Page)
            if ($request->input('mode') === 'admin') {
                $isSuperUser = true;
            }
            
            // Or check JWT Role
            if (!$isSuperUser) {
                $payload = auth()->payload();
                $role = $payload->get('role');
                if (in_array($role, ['admin', 'IT', 'direksi'])) {
                    $isSuperUser = true;
                }
            }
        } catch (\Exception $e) {
            // Ignore if payload fails
        }

        $downstreamDepts = [];
        
        if ($user && $user->detail) {
            // Only apply hierarchy if NOT super user
            if (!$isSuperUser) {
                $approver = $user->detail;
                
                // Get downstream departments where current user's department AND job matches 'UP'
                $downstreamDepts = DB::table('rsia_mapping_jabatan')
                    ->where('dep_id_up', $approver->departemen)
                    ->where('kd_jabatan_up', $approver->jnj_jabatan)
                    ->pluck('dep_id_down')
                    ->unique()
                    ->values()
                    ->toArray();
                
                // If user has downstream departments, filter query to show only employees in those departments
                if (!empty($downstreamDepts)) {
                    $query->whereIn('departemen', $downstreamDepts);
                } 
            }
        }

        if ($departemen && $departemen !== 'all') {
            $query->where('departemen', $departemen);
        }

        if ($search) {
            $query->where('nama', 'like', "%{$search}%");
        }

        // Get employees
        $pegawai = $query->orderBy('nama')->get();

        // Get schedules from DRAFT table (RsiaJadwalPegawai)
        // ... existing schedule fetching ...
        // Get schedules from DRAFT table (RsiaJadwalPegawai)
        $drafts = RsiaJadwalPegawai::where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->whereIn('id', $pegawai->pluck('id'))
            ->get()
            ->keyBy('id');

        // Get schedules from FINAL table (JadwalPegawai) as fallback
        $finals = JadwalPegawai::where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->whereIn('id', $pegawai->pluck('id'))
            ->get()
            ->keyBy('id');
        // Merge schedule into employee objects
        $result = $pegawai->map(function($p) use ($drafts, $finals) {
            $sched = $drafts->get($p->id) ?? $finals->get($p->id);
            
            // Explicitly convert to array and force include id if hidden
            $p->makeVisible(['id']); 
            $data = $p->toArray();
            
            // Override 'departemen' code with name if available, or keep code as fallback
            if ($p->dep) {
                $data['departemen'] = $p->dep->nama;
            }

            $data['jadwal'] = $sched ? $sched : null;
            return $data;
        });

        // Add authorized departments to response for frontend filter
        $additionalMeta = [];
        
        if ($isSuperUser) {
             // Admin/IT sees ALL departments
             $allDepts = DB::table('departemen')
                ->select('dep_id as id', 'nama as name')
                ->orderBy('nama')
                ->get();
             $additionalMeta['authorized_departments'] = $allDepts;
        } elseif (!empty($downstreamDepts)) {
            // Normal user sees downstream only
            $deptsWithNames = DB::table('departemen')
                ->whereIn('dep_id', $downstreamDepts)
                ->select('dep_id as id', 'nama as name')
                ->orderBy('nama')
                ->get();

            $additionalMeta['authorized_departments'] = $deptsWithNames;
        }

        return ApiResponse::successWithData($result, 'Data pengajuan jadwal berhasil diambil', $additionalMeta);
    }

    public function getShifts()
    {
        $shifts = JamMasuk::orderBy('shift', 'asc')->get();
        return ApiResponse::successWithData($shifts, 'Data shift berhasil diambil');
    }

    // SAVE TO DRAFT (SUBMISSION)
    public function store(Request $request)
    {
        $bulanRaw = $request->input('bulan');
        $bulan = sprintf('%02d', $bulanRaw);
        $tahun = $request->input('tahun');
        $items = $request->input('data'); 

        if (!$items || !is_array($items)) {
             return ApiResponse::error('Invalid data format', 'invalid_data', null, 400);
        }

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                // Find or init
                $draft = RsiaJadwalPegawai::firstOrNew([
                    'id' => $item['id'],
                    'bulan' => $bulan,
                    'tahun' => $tahun
                ]);

                // If new, initialize all h1..h31 to empty string (matches enum '' option)
                // This prevents DB from defaulting to first enum 'Pagi'
                if (!$draft->exists) {
                    for ($i = 1; $i <= 31; $i++) {
                        $draft->{'h' . $i} = ''; 
                    }
                }

                // Apply changes from request
                for ($i = 1; $i <= 31; $i++) {
                    $key = 'h' . $i; 
                    if (array_key_exists($key, $item)) {
                        $val = $item[$key];
                        $draft->{$key} = is_null($val) ? '' : $val;
                    }
                }

                $draft->save();
            }
            DB::commit();
            return ApiResponse::success('Pengajuan jadwal berhasil disimpan (Draft)');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Gagal menyimpan pengajuan: ' . $e->getMessage(), 'save_error', null, 500);
        }
    }

    // APPROVE / PUBLISH SCHEDULE
    // Copies data from RsiaJadwalPegawai (Draft) to JadwalPegawai (Final)
    public function approve(Request $request)
    {
        $bulanRaw = $request->input('bulan');
        $bulan = sprintf('%02d', $bulanRaw);
        $tahun = $request->input('tahun');
        $departemen = $request->input('departemen');

        if (!$bulan || !$tahun) {
            return ApiResponse::error('Bulan dan Tahun harus diisi', 'validation_error', null, 400);
        }

        DB::beginTransaction();
        try {
            // Build query
            $query = RsiaJadwalPegawai::where('bulan', $bulan)
                ->where('tahun', $tahun);
            
            // Hierarchical Approval Logic (Department Based)
            $user = auth()->user();
            if ($user && $user->detail) {
                $approver = $user->detail;
                
                // Get downstream departments
                $downstreamDepts = DB::table('rsia_mapping_jabatan')
                    ->where('dep_id_up', $approver->departemen)
                    ->where('kd_jabatan_up', $approver->jnj_jabatan)
                    ->pluck('dep_id_down')
                    ->unique()
                    ->values()
                    ->toArray();
                
                if (!empty($downstreamDepts)) {
                    $query->whereHas('pegawai', function($q) use ($downstreamDepts) {
                        $q->whereIn('departemen', $downstreamDepts);
                    });
                } else {
                    // Fallback: If no mapping, user sees nothing or restricted? 
                    // Based on "misal ... yang muncul departemen dibawah saya", 
                    // if there are no departments mapped "below", then logically they shouldn't approve anything.
                    // However, for admin roles or unmapped approval roles, this might be too strict.
                    // But adhering to the request:
                    // $query->whereRaw('0 = 1'); 
                }
            }
            
            // Filter by department if specified
            if ($departemen && $departemen !== 'all') {
                $query->whereHas('pegawai', function($q) use ($departemen) {
                    $q->where('departemen', $departemen);
                });
            }

            $drafts = $query->get();

            if ($drafts->isEmpty()) {
                // No drafts to approve implies nothing to do, or maybe success with 0 count
                return ApiResponse::success('Tidak ada data pengajuan untuk disetujui pada periode ini');
            }

            foreach ($drafts as $draft) {
                // Prepare final data
                $finalData = $draft->toArray();
                
                // Cleanup timestamp/id issues if strictly copying array (though updateOrCreate handles fillables)
                // Remove created_at/updated_at if not matching target schema or let Eloquent handle it
                unset($finalData['created_at'], $finalData['updated_at']);

                // Update FINAL table
                JadwalPegawai::updateOrCreate(
                    [
                        'id' => $draft->id,
                        'bulan' => $bulan,
                        'tahun' => $tahun
                    ],
                    $finalData
                );
            }

            DB::commit();
            return ApiResponse::success('Jadwal berhasil disetujui dan dipublikasikan');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Gagal menyetujui jadwal: ' . $e->getMessage(), 'approve_error', null, 500);
        }
    }

    // SAVE TO BOTH DRAFT AND FINAL (ADMIN DIRECT INPUT)
    public function storeAdmin(Request $request)
    {
        $bulanRaw = $request->input('bulan');
        $bulan = sprintf('%02d', $bulanRaw);
        $tahun = $request->input('tahun');
        $items = $request->input('data'); 

        if (!$items || !is_array($items)) {
             return ApiResponse::error('Invalid data format', 'invalid_data', null, 400);
        }

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                // Save to DRAFT table
                $draft = RsiaJadwalPegawai::firstOrNew([
                    'id' => $item['id'],
                    'bulan' => $bulan,
                    'tahun' => $tahun
                ]);

                if (!$draft->exists) {
                    for ($i = 1; $i <= 31; $i++) {
                        $draft->{'h' . $i} = ''; 
                    }
                }

                for ($i = 1; $i <= 31; $i++) {
                    $key = 'h' . $i; 
                    if (array_key_exists($key, $item)) {
                        $val = $item[$key];
                        $draft->{$key} = is_null($val) ? '' : $val;
                    }
                }

                $draft->save();

                // Save to FINAL table (same data)
                $final = JadwalPegawai::firstOrNew([
                    'id' => $item['id'],
                    'bulan' => $bulan,
                    'tahun' => $tahun
                ]);

                for ($i = 1; $i <= 31; $i++) {
                    $key = 'h' . $i; 
                    if (array_key_exists($key, $item)) {
                        $val = $item[$key];
                        $final->{$key} = is_null($val) ? '' : $val;
                    }
                }

                $final->save();
            }
            
            DB::commit();
            return ApiResponse::success('Jadwal berhasil disimpan (Draft & Final)');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Gagal menyimpan jadwal: ' . $e->getMessage(), 'save_error', null, 500);
        }
    }
}
