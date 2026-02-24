<?php

namespace App\Http\Controllers\v2;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pegawai;
use App\Models\JadwalTambahan;     // Final Table
use App\Models\RsiaJadwalTambahan; // Draft/Submission Table
use App\Models\JamMasuk;
use Illuminate\Support\Facades\DB;

class JadwalTambahanController extends Controller
{
    // FETCH SUBMISSION/DRAFT SCHEDULE
    public function index(Request $request) 
    {
        $bulan = $request->input('bulan', date('m'));
        $tahun = $request->input('tahun', date('Y'));
        $departemen = $request->input('departemen');
        $search = $request->input('search');

        // Query Pegawai
        $query = Pegawai::query()
            ->select('id', 'nik', 'nama', 'departemen', 'jbtn')
            ->with('dep:dep_id,nama') // Eager load department name
            ->where('stts_aktif', 'AKTIF');

        $isSuperUser = false;
        try {
            if ($request->input('mode') === 'admin') {
                $isSuperUser = true;
            }
            if (!$isSuperUser) {
                $payload = auth()->payload();
                $role = $payload->get('role');
                if (in_array($role, ['admin', 'IT', 'direksi'])) {
                    $isSuperUser = true;
                }
            }
        } catch (\Exception $e) {}

        // Hierarchical Approval Logic
        // Filter employees based on who the current user can approve (Department based only)
        $user = auth()->user();
        $downstreamDepts = [];
        if (!$isSuperUser && $user && $user->detail) {
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

        if ($departemen && $departemen !== 'all') {
            $query->where('departemen', $departemen);
        }

        if ($search) {
            $query->where('nama', 'like', "%{$search}%");
        }

        // Get employees
        $pegawai = $query->orderBy('nama')->get();

        // Get schedules from DRAFT table (RsiaJadwalTambahan)
        $jadwal = RsiaJadwalTambahan::where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->whereIn('id', $pegawai->pluck('id'))
            ->get()
            ->keyBy('id');

        // Merge schedule into employee objects
        $result = $pegawai->map(function($p) use ($jadwal) {
            $sched = $jadwal->get($p->id);
            
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
            // Fetch names for dropdown
            $deptsWithNames = DB::table('departemen')
                ->whereIn('dep_id', $downstreamDepts)
                ->select('dep_id as id', 'nama as name')
                ->orderBy('nama')
                ->get();

            $additionalMeta['authorized_departments'] = $deptsWithNames;
        }

        return ApiResponse::successWithData($result, 'Data pengajuan jadwal tambahan berhasil diambil', $additionalMeta);
    }

    // SAVE TO DRAFT (SUBMISSION)
    public function store(Request $request)
    {
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');
        $items = $request->input('data'); 

        if (!$items || !is_array($items)) {
             return ApiResponse::error('Invalid data format', 'invalid_data', null, 400);
        }

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                // Find or init
                $draft = RsiaJadwalTambahan::firstOrNew([
                    'id' => $item['id'],
                    'bulan' => $bulan,
                    'tahun' => $tahun
                ]);

                // If new, initialize all h1..h31 to empty string
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
            return ApiResponse::success('Pengajuan jadwal tambahan berhasil disimpan (Draft)');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Gagal menyimpan pengajuan: ' . $e->getMessage(), 'save_error', null, 500);
        }
    }

    // APPROVE / PUBLISH SCHEDULE
    // Copies data from RsiaJadwalTambahan (Draft) to JadwalTambahan (Final)
    public function approve(Request $request)
    {
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');
        $departemen = $request->input('departemen');

        if (!$bulan || !$tahun) {
            return ApiResponse::error('Bulan dan Tahun harus diisi', 'validation_error', null, 400);
        }

        DB::beginTransaction();
        try {
            // Build query
            $query = RsiaJadwalTambahan::where('bulan', $bulan)
                ->where('tahun', $tahun);
            
            $isSuperUser = false;
            try {
                if ($request->input('mode') === 'admin') {
                    $isSuperUser = true;
                }
                if (!$isSuperUser) {
                    $payload = auth()->payload();
                    $role = $payload->get('role');
                    if (in_array($role, ['admin', 'IT', 'direksi'])) {
                        $isSuperUser = true;
                    }
                }
            } catch (\Exception $e) {}

            // Hierarchical Approval Logic (Department Based)
            $user = auth()->user();
            if (!$isSuperUser && $user && $user->detail) {
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
                return ApiResponse::success('Tidak ada data pengajuan tambahan untuk disetujui pada periode ini');
            }

            foreach ($drafts as $draft) {
                // Prepare final data
                $finalData = $draft->toArray();
                
                unset($finalData['created_at'], $finalData['updated_at']);

                // Update FINAL table
                JadwalTambahan::updateOrCreate(
                    [
                        'id' => $draft->id,
                        'bulan' => $bulan,
                        'tahun' => $tahun
                    ],
                    $finalData
                );
            }

            DB::commit();
            return ApiResponse::success('Jadwal tambahan berhasil disetujui dan dipublikasikan');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Gagal menyetujui jadwal: ' . $e->getMessage(), 'approve_error', null, 500);
        }
    }
}
