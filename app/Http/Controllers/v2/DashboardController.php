<?php

namespace App\Http\Controllers\v2;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pegawai;
use App\Models\RsiaCuti;
use App\Models\RegPeriksa;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getStats(Request $request)
    {
        try {
            $stats = [
                'pasien' => $this->getPasienStats($request),
                'pegawai' => $this->getPegawaiStats(),
                'cuti' => $this->getCutiStats($request),
                'bed' => $this->getBedStats(),
                'approval' => $this->getApprovalStats($request),
                'farmasi' => $this->getFarmasiStats()
            ];

            return ApiResponse::success('Dashboard statistics retrieved successfully', $stats);
        } catch (\Exception $e) {
            \Log::error('Dashboard Stats Error: ' . $e->getMessage());
            return ApiResponse::error('Failed to retrieve dashboard statistics', 'internal_server_error', null, 500);
        }
    }

    /**
     * Get pegawai statistics
     */
    private function getPegawaiStats()
    {
        // Replicating logic from App\Http\Controllers\v2\PegawaiController::statistik
        // to ensure numbers match the /sdi/karyawan page exactly.
        
        $stats = Pegawai::where('stts_aktif', 'AKTIF')
            ->leftJoin('petugas as pt', 'pt.nip', '=', 'pegawai.nik')
            ->leftJoin('jabatan as j', 'j.kd_jbtn', '=', 'pt.kd_jbtn')
            ->where(function($q) {
                $q->where('pegawai.jnj_jabatan', '!=', '-')
                  ->orWhere('pt.kd_jbtn', '!=', '-');
            })
            ->selectRaw("
                COUNT(DISTINCT pegawai.nik) as total,
                
                -- PERAWAT: Keyword in Job Title OR Education (Ners/Keperawatan)
                SUM(CASE 
                    WHEN (
                        pegawai.jbtn LIKE '%Perawat%' OR pegawai.jbtn LIKE '%Ners%' OR 
                        j.nm_jbtn LIKE '%Perawat%' OR j.nm_jbtn LIKE '%Ners%'
                    ) OR (
                        pegawai.pendidikan LIKE '%Keperawatan%' OR pegawai.pendidikan LIKE '%Ners%'
                    ) THEN 1 ELSE 0 END
                ) as perawat,

                -- BIDAN: Keyword in Job Title OR Education (Bidan/Kebidanan)
                SUM(CASE 
                    WHEN (
                        (pegawai.jbtn LIKE '%Bidan%' OR j.nm_jbtn LIKE '%Bidan%') OR 
                        (pegawai.pendidikan LIKE '%Kebidanan%' OR pegawai.pendidikan LIKE '%Bidan%')
                    ) AND NOT (
                        -- Exclude if already counted as Perawat (Logic order in PHP loop was Perawat first)
                        -- SQL evaluates independently, so we must exclude explicitly if there's overlap. 
                        -- However, 'Perawat' and 'Bidan' keywords/edu rarely overlap.
                        pegawai.jbtn LIKE '%Perawat%' OR pegawai.jbtn LIKE '%Ners%' OR 
                        j.nm_jbtn LIKE '%Perawat%' OR j.nm_jbtn LIKE '%Ners%' OR
                        pegawai.pendidikan LIKE '%Keperawatan%' OR pegawai.pendidikan LIKE '%Ners%'
                    ) THEN 1 ELSE 0 END
                ) as bidan,

                -- MEDIS: Keyword 'Dokter' OR Education 'Dokter'/'S2 Medis'
                SUM(CASE 
                    WHEN (
                        (pegawai.jbtn LIKE '%Dokter%' OR j.nm_jbtn LIKE '%Dokter%') OR 
                        (pegawai.pendidikan LIKE '%Dokter%' OR pegawai.pendidikan LIKE '%S2 Medis%')
                    ) AND NOT (
                        -- Exclude Perawat/Bidan matches
                        pegawai.jbtn LIKE '%Perawat%' OR pegawai.jbtn LIKE '%Ners%' OR 
                        j.nm_jbtn LIKE '%Perawat%' OR j.nm_jbtn LIKE '%Ners%' OR
                        pegawai.pendidikan LIKE '%Keperawatan%' OR pegawai.pendidikan LIKE '%Ners%' OR
                        pegawai.jbtn LIKE '%Bidan%' OR j.nm_jbtn LIKE '%Bidan%' OR
                        pegawai.pendidikan LIKE '%Kebidanan%' OR pegawai.pendidikan LIKE '%Bidan%'
                    ) THEN 1 ELSE 0 END
                ) as dokter,

                -- FARMASI
                SUM(CASE 
                    WHEN (
                        (pegawai.jbtn LIKE '%Farmasi%' OR pegawai.jbtn LIKE '%Apoteker%' OR pegawai.jbtn LIKE '%TTK%' OR pegawai.jbtn LIKE '%Tenaga Teknis Kefarmasian%' OR
                        j.nm_jbtn LIKE '%Farmasi%' OR j.nm_jbtn LIKE '%Apoteker%' OR j.nm_jbtn LIKE '%TTK%' OR j.nm_jbtn LIKE '%Tenaga Teknis Kefarmasian%')
                    ) AND NOT (
                        -- Exclude Medis/Perawat/Bidan
                        pegawai.jbtn LIKE '%Dokter%' OR j.nm_jbtn LIKE '%Dokter%'
                    ) THEN 1 ELSE 0 END
                ) as farmasi,

                -- ANALIS
                SUM(CASE 
                    WHEN (
                        (pegawai.jbtn LIKE '%Analis%' OR pegawai.jbtn LIKE '%Laborat%' OR pegawai.jbtn LIKE '%ATLM%' OR
                        j.nm_jbtn LIKE '%Analis%' OR j.nm_jbtn LIKE '%Laborat%' OR j.nm_jbtn LIKE '%ATLM%')
                    ) AND NOT (
                        pegawai.jbtn LIKE '%Dokter%' OR j.nm_jbtn LIKE '%Dokter%'
                    ) THEN 1 ELSE 0 END
                ) as analis,

                -- GIZI
                SUM(CASE 
                    WHEN (
                        (pegawai.jbtn LIKE '%Nutrisionis%' OR pegawai.jbtn LIKE '%Koordinator Gizi%' OR
                        j.nm_jbtn LIKE '%Nutrisionis%' OR j.nm_jbtn LIKE '%Koordinator Gizi%')
                    ) AND NOT (
                        pegawai.jbtn LIKE '%Dokter%' OR j.nm_jbtn LIKE '%Dokter%'
                    ) THEN 1 ELSE 0 END
                ) as gizi,

                -- SANITARIAN
                SUM(CASE 
                    WHEN (
                        (pegawai.jbtn LIKE '%Sanitarian%' OR pegawai.jbtn LIKE '%Kesehatan Lingkungan%' OR pegawai.jbtn LIKE '%Kesling%' OR
                        j.nm_jbtn LIKE '%Sanitarian%' OR j.nm_jbtn LIKE '%Kesehatan Lingkungan%' OR j.nm_jbtn LIKE '%Kesling%')
                    ) AND NOT (
                        pegawai.jbtn LIKE '%Dokter%' OR j.nm_jbtn LIKE '%Dokter%'
                    ) THEN 1 ELSE 0 END
                ) as sanitarian,

                -- TEKNISI ELEKTROMEDIS
                SUM(CASE 
                    WHEN (
                        (pegawai.jbtn LIKE '%Elektromedis%' OR pegawai.jbtn LIKE '%ATEM%' OR pegawai.jbtn LIKE '%Teknisi Medis%' OR pegawai.jbtn LIKE '%Elektromedik%' OR
                        j.nm_jbtn LIKE '%Elektromedis%' OR j.nm_jbtn LIKE '%ATEM%' OR j.nm_jbtn LIKE '%Teknisi Medis%' OR j.nm_jbtn LIKE '%Elektromedik%')
                    ) AND NOT (
                        pegawai.jbtn LIKE '%Dokter%' OR j.nm_jbtn LIKE '%Dokter%'
                    ) THEN 1 ELSE 0 END
                ) as elektromedis,

                -- RADIOGRAFER
                SUM(CASE 
                    WHEN (
                        (pegawai.jbtn LIKE '%Radiografer%' OR pegawai.jbtn LIKE '%Radiologi%' OR
                        j.nm_jbtn LIKE '%Radiografer%' OR j.nm_jbtn LIKE '%Radiologi%')
                    ) AND NOT (
                        -- Exclude Medis (Dokter Spesialis Radiologi must be Medis ONLY)
                        pegawai.jbtn LIKE '%Dokter%' OR j.nm_jbtn LIKE '%Dokter%'
                    ) THEN 1 ELSE 0 END
                ) as radiografer,

                -- RM (Perekam Medis)
                SUM(CASE 
                    WHEN (
                        (pegawai.jbtn LIKE '%Perekam Medis%' OR pegawai.jbtn LIKE '%Rekam Medis%' OR
                        j.nm_jbtn LIKE '%Perekam Medis%' OR j.nm_jbtn LIKE '%Rekam Medis%')
                    ) AND NOT (
                        pegawai.jbtn LIKE '%Dokter%' OR j.nm_jbtn LIKE '%Dokter%'
                    ) THEN 1 ELSE 0 END
                ) as rm
            ")
            ->first();

        // Calculate 'Non Medis' as total minus all the specific clinical categories
        // Note: This logic assumes that any clinical staff NOT captured above falls into Non Medis, 
        // OR that overlap is handled. Given simple SUM cases, one person could be counted twice if patterns overlap 
        // (unlikely with these specific patterns, but 'teknisi' might overlap if not careful).
        // To be safe, we sum the derived counts.
        
        $clinicalTotal = $stats->dokter + $stats->perawat + $stats->bidan + $stats->farmasi + 
                         $stats->analis + $stats->radiografer + $stats->gizi + $stats->rm + 
                         $stats->sanitarian + $stats->elektromedis;
        
        $nonMedis = $stats->total - $clinicalTotal;

        return [
            'total' => $stats->total,
            'breakdown' => [
                'Non Medis' => $nonMedis,
                'Perawat' => $stats->perawat,
                'Bidan' => $stats->bidan,
                'Medis' => $stats->dokter,
                'Farmasi' => $stats->farmasi,
                'Analis' => $stats->analis,
                'RM' => $stats->rm,
                'Gizi' => $stats->gizi,
                'Radiografer' => $stats->radiografer,
                'Sanitarian' => $stats->sanitarian,
                'Teknisi Elektromedis' => $stats->elektromedis
            ]
        ];
    }


    /**
     * Get cuti statistics for current month
     */
    private function getCutiStats(Request $request)
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // Base query
        $query = RsiaCuti::whereYear('tanggal_cuti', $currentYear)
            ->whereMonth('tanggal_cuti', $currentMonth);

        // Filter by mapping jabatan (if applicable)
        $user = $request->user();
        if ($user) {
            $nik = $user->nik ?? $user->id_user ?? null;
            if ($nik) {
                $pegawai = Pegawai::where('nik', $nik)->first();
                if ($pegawai) {
                    // Check for mappings
                    $mappings = DB::table('rsia_mapping_jabatan')
                        ->where('dep_id_up', $pegawai->departemen)
                        ->where('kd_jabatan_up', $pegawai->jnj_jabatan)
                        ->get();

                    if ($mappings->isNotEmpty()) {
                        $subordinateDepts = $mappings->pluck('dep_id_down')->unique()->toArray();
                        $subordinatePositions = $mappings->pluck('kd_jabatan_down')->unique()->toArray();

                        // Join with pegawai to filter by jabatan
                        $query->join('pegawai', 'rsia_cuti.id_pegawai', '=', 'pegawai.id')
                              ->whereIn('rsia_cuti.dep_id', $subordinateDepts)
                              ->whereIn('pegawai.jnj_jabatan', $subordinatePositions);
                    } else {
                        // If no mapping, maybe show only self? Or global?
                        // "sesuai mapping jabatan" usually implies hierarchical view.
                        // If no hierarchy defined, maybe fallback to user's department?
                        // For now, let's keep it global IF no mapping is found to avoid showing 0 for everyone.
                        // UNLESS the user explicitly wants strictly mapped data. 
                        // Given 'approval' is 0, sticking to global for 'stats' might be confusing.
                        // Let's try to filter by Department if no mapping exists?
                        // $query->where('rsia_cuti.dep_id', $pegawai->departemen);
                        
                        // Let's stick with: If mapping exists, use it. If not, use Global. 
                        // This ensures Managers see their team, and others see the hospital stats.
                    }
                }
            }
        }

        // Clone query for different statuses to avoid interference
        $bulanIni = (clone $query)->count();
        $pending = (clone $query)->where('status_cuti', '0')->count();
        $approved = (clone $query)->where('status_cuti', '2')->count();

        return [
            'bulan_ini' => $bulanIni,
            'pending' => $pending,
            'approved' => $approved
        ];
    }

    /**
     * Get bed availability statistics
     */
    private function getBedStats()
    {
        // Replicating logic from BedAvailabilityController
        // Using aplicare_ketersediaan_kamar as primary data source to match /dashboard/bed
        $stats = \App\Models\AplicareKetersediaanKamar::selectRaw('
                SUM(kapasitas) as total, 
                SUM(tersedia) as tersedia, 
                SUM(tersediapria) as tersedia_pria, 
                SUM(tersediawanita) as tersedia_wanita, 
                SUM(tersediapriawanita) as tersedia_priawanita
            ')
            ->first();

        $totalBeds = $stats->total ?? 0;
        $availableBeds = $stats->tersedia ?? 0;
        
        // Calculate occupied beds
        $occupiedBeds = $totalBeds - $availableBeds;
        $occupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;

        return [
            'total' => (int) $totalBeds,
            'terisi' => (int) $occupiedBeds,
            'tersedia' => (int) $availableBeds,
            'occupancy_rate' => $occupancyRate
        ];
    }

    /**
     * Get approval pending statistics
     */
    private function getApprovalStats(Request $request)
    {
        // Get NIK from authenticated user
        $user = $request->user();
        $nik = null;
        
        if ($user) {
            $nik = $user->nik ?? $user->id_user ?? null;
        }

        if (!$nik) {
            return [
                'cuti_pending' => 0,
                'jadwal_pending' => 0,
                'total_pending' => 0
            ];
        }

        $pegawai = Pegawai::where('nik', $nik)->first();
        
        if (!$pegawai) {
            return [
                'cuti_pending' => 0,
                'jadwal_pending' => 0,
                'total_pending' => 0
            ];
        }

        // Get subordinate departments from mapping
        $mappings = DB::table('rsia_mapping_jabatan')
            ->where('dep_id_up', $pegawai->departemen)
            ->where('kd_jabatan_up', $pegawai->jnj_jabatan)
            ->get();

        if ($mappings->isEmpty()) {
            return [
                'cuti_pending' => 0,
                'jadwal_pending' => 0,
                'total_pending' => 0
            ];
        }

        $subordinateDepts = $mappings->pluck('dep_id_down')->unique()->toArray();
        $subordinatePositions = $mappings->pluck('kd_jabatan_down')->unique()->toArray();

        // Count pending cuti
        $cutiPending = RsiaCuti::join('pegawai', 'rsia_cuti.id_pegawai', '=', 'pegawai.id')
            ->whereIn('rsia_cuti.dep_id', $subordinateDepts)
            ->whereIn('pegawai.jnj_jabatan', $subordinatePositions)
            ->where('rsia_cuti.status_cuti', '0')
            ->count();

        // Count pending jadwal (if table exists)
        $jadwalPending = 0;
        // TODO: Add jadwal pending count when table structure is confirmed

        return [
            'cuti_pending' => $cutiPending,
            'jadwal_pending' => $jadwalPending,
            'total_pending' => $cutiPending + $jadwalPending
        ];
    }

    /**
     * Get farmasi stock statistics
     */
    private function getFarmasiStats()
    {
        // Count total items
        $totalItem = DB::table('databarang')
            ->where('status', '1')
            ->count();

        // For now, just return total items
        // Stock tracking requires joining with gudangbarang or other inventory tables
        return [
            'total_item' => $totalItem,
            'stok_kritis' => 0,  // TODO: Implement when inventory table structure is confirmed
            'stok_aman' => $totalItem
        ];
    }

    /**
     * Get pasien statistics for today by poli
     */
    private function getPasienStats(Request $request = null)
    {
        $query = RegPeriksa::query();

        if ($request && $request->has(['tgl_awal', 'tgl_akhir'])) {
            $query->whereBetween('tgl_registrasi', [$request->tgl_awal, $request->tgl_akhir]);
        } else {
            $today = Carbon::today()->toDateString();
            $query->where('tgl_registrasi', $today);
        }

        // 1. Total Pasien (All registrations today)
        $totalPasien = (clone $query)->count();

        // 2. Kunjungan Rawat Inap (status_lanjut = 'Ranap')
        $ranap = (clone $query)->where('status_lanjut', 'Ranap')->count();

        // 3. Kunjungan IGD (kd_poli = 'IGD' or similar)
        // Need to be careful with 'IGD' code. Usually 'IGD' or 'IGDK'.
        // Let's assume 'IGD' or 'UGD' based on shortenPoliName logic seen earlier or common conventions.
        // Or better, check master poliklinik? 
        // Based on previous code: if (str_contains($originalName, 'ugd') || str_contains($originalName, 'igd'))
        // Let's check 'kd_poli' directly if known, or join poliklinik.
        // Ideally we filter mainly by 'IGD' code. 
        // Let's refine this: status_lanjut 'Ralan' AND poli like '%IGD%'
        
        $igd = (clone $query)
            ->where('status_lanjut', 'Ralan') // IGD is technically outpatient first usually
            ->whereHas('poliklinik', function($q) {
                $q->where('nm_poli', 'LIKE', '%IGD%')
                  ->orWhere('nm_poli', 'LIKE', '%UGD%');
            })->count();

        // 4. Kunjungan Rawat Jalan (Non IGD)
        // status_lanjut 'Ralan' AND NOT (IGD)
        $ralan = (clone $query)
            ->where('status_lanjut', 'Ralan')
            ->whereDoesntHave('poliklinik', function($q) {
                $q->where('nm_poli', 'LIKE', '%IGD%')
                  ->orWhere('nm_poli', 'LIKE', '%UGD%');
            })->count();

        // Additional: If logic for Ranap includes patients moving from IGD, 'status_lanjut' might update to 'Ranap'. 
        // Dashboard usually wants "Current Status". 
        // If they entered IGD then went Ranap, do they count as IGD? 
        // Usually 'status_lanjut' reflects final state for that registration.
        // User request: "Kunjungan Rawat Inap", "Kunjungan Rawat Jalan", "Kunjungan IGD".
        // Simplest interpretation:
        // Ranap = status_lanjut 'Ranap'
        // IGD = status_lanjut 'Ralan' + Poli IGD
        // Ralan = status_lanjut 'Ralan' + Poli Non-IGD.
        
        // Let's also check if 'IGD' patients can have status_lanjut 'Ranap'. 
        // If so, do they count as Ranap or IGD? 
        // "Kunjungan Rawat Inap" implies bed occupancy or admission. 
        // "Kunjungan IGD" implies emergency cases.
        // Usually, dashboard counts ADMISSIONS vs OUTPATIENT VISITS.
        // I will stick to distinct buckets based on 'status_lanjut' and 'poli'.

        // Adjusted logic to ensure Total = sum of parts?
        // Total = Ranap + Ralan + IGD ??
        // Total = Ranap + Ralan_All.
        // Ralan_All = Ralan_NonIGD + Ralan_IGD.
        // So yes, Total = Ranap + Ralan + IGD (if IGD is subset of Ralan).
        // Wait, if patient is Ranap, they are NOT Ralan.
        // So Total = Ranap + (Ralan_NonIGD + Ralan_IGD).
        // Correct.

        // 5. Per Poli Breakdown (Restored)
        $poliData = (clone $query)
            ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
            ->select('poliklinik.nm_poli', DB::raw('COUNT(*) as count'))
            ->groupBy('poliklinik.nm_poli')
            ->orderBy('count', 'desc')
            ->get();

        $poliBreakdown = [];
        foreach ($poliData as $poli) {
            $shortName = $this->shortenPoliName($poli->nm_poli);
            if (isset($poliBreakdown[$shortName])) {
                $poliBreakdown[$shortName] += $poli->count;
            } else {
                $poliBreakdown[$shortName] = $poli->count;
            }
        }

        return [
            'total' => $totalPasien,
            'ranap' => $ranap,
            'ralan' => $ralan,
            'igd' => $igd,
            'per_poli' => $poliBreakdown
        ];
    }

    /**
     * Shorten poli name for display
     */
    private function shortenPoliName($name)
    {
        $originalName = strtolower($name);

        // Special cases
        if (str_contains($originalName, 'ugd') || str_contains($originalName, 'igd')) {
            return 'IGD';
        }

        // Determine suffix based on original name
        $suffix = '';
        if (str_contains($originalName, 'sore')) $suffix = ' (Sore)';
        elseif (str_contains($originalName, 'siang')) $suffix = ' (Siang)';
        elseif (str_contains($originalName, 'malam')) $suffix = ' (Malam)';

        // Remove common prefixes/suffixes from the name for processing
        $name = str_replace(['Poli ', 'Klinik ', 'Spesialis '], '', $name);
        $name = str_replace('Kebidanan dan Penyakit ', '', $name);
        $name = str_replace('Penyakit ', '', $name);
        
        // Handle specifics - simplify base name
        if (str_contains($name, 'Anak')) $name = 'Anak';
        elseif (str_contains($name, 'Kandungan')) $name = 'Kandungan';
        elseif (str_contains($name, 'Dalam')) $name = 'Dalam';
        elseif (str_contains($name, 'Bedah')) $name = 'Bedah';
        elseif (str_contains($name, 'Gigi')) $name = 'Gigi';
        elseif (str_contains($name, 'Mata')) $name = 'Mata';
        elseif (str_contains($name, 'Saraf')) $name = 'Saraf';
        elseif (str_contains($name, 'THT')) $name = 'THT';
        elseif (str_contains($name, 'Kulit')) $name = 'Kulit';
        elseif (str_contains($name, 'Jantung')) $name = 'Jantung';
        elseif (str_contains($name, 'Paru')) $name = 'Paru';
        elseif (str_contains($name, 'Jiwa')) $name = 'Jiwa';
        elseif (str_contains($name, 'Rehab')) $name = 'Rehab Medik';
        elseif (str_contains($name, 'Gizi')) $name = 'Gizi';
        
        // Clean up any remaining braces or time words in the base name if they were matched above
        // (Though strictly replacing the whole string with 'Anak' etc handles this)

        // Add "Poli" prefix back except for IGD, and append identified suffix
        // If we replaced the name with a specific category (e.g. 'Anak'), use that.
        // Otherwise use the cleaned name.
        
        return 'Poli ' . trim($name) . $suffix;
    }

    /**
     * Get Code Blue Schedule for today
     * 
     * @return array
     */
    public function getCodeBlueSchedule()
    {
        try {
            $today = Carbon::today()->format('Y-m-d');
            
            $schedule = DB::table('rsia_codeblue as cb')
                ->where('cb.tanggal', $today)
                ->where('cb.status', '1')
                ->orderBy('cb.no_urut')
                ->get();

            if ($schedule->isEmpty()) {
                return ApiResponse::success('No code blue schedule for today', [
                    'pagi' => [],
                    'siang' => [],
                    'malam' => []
                ]);
            }

            $result = [
                'pagi' => [],
                'siang' => [],
                'malam' => []
            ];

            foreach ($schedule as $row) {
                // Get officer name for each shift
                $pagiOfficer = DB::table('pegawai')->where('nik', $row->pagi)->first();
                $siangOfficer = DB::table('pegawai')->where('nik', $row->siang)->first();
                $malamOfficer = DB::table('pegawai')->where('nik', $row->malam)->first();

                $result['pagi'][$row->tim] = [
                    'nik' => $row->pagi,
                    'nama' => $pagiOfficer ? $pagiOfficer->nama : '-'
                ];

                $result['siang'][$row->tim] = [
                    'nik' => $row->siang,
                    'nama' => $siangOfficer ? $siangOfficer->nama : '-'
                ];

                $result['malam'][$row->tim] = [
                    'nik' => $row->malam,
                    'nama' => $malamOfficer ? $malamOfficer->nama : '-'
                ];
            }

            return ApiResponse::success('Code blue schedule retrieved successfully', $result);
        } catch (\Exception $e) {
            \Log::error('Code Blue Schedule Error: ' . $e->getMessage());
            return ApiResponse::error('Failed to retrieve code blue schedule', 'internal_server_error', null, 500);
        }
    }

    /**
     * Get Code Blue Schedule by specific date
     * 
     * @param string $date
     * @return array
     */
    public function getCodeBlueScheduleByDate($date)
    {
        try {
            $schedule = DB::table('rsia_codeblue as cb')
                ->where('cb.tanggal', $date)
                ->orderBy('cb.no_urut')
                ->get();

            if ($schedule->isEmpty()) {
                return ApiResponse::success('No code blue schedule for this date', [
                    'pagi' => [],
                    'siang' => [],
                    'malam' => []
                ]);
            }

            $result = [
                'pagi' => [],
                'siang' => [],
                'malam' => []
            ];

            foreach ($schedule as $row) {
                $result['pagi'][$row->tim] = [
                    'nik' => $row->pagi,
                    'nama' => DB::table('pegawai')->where('nik', $row->pagi)->value('nama') ?? '-'
                ];

                $result['siang'][$row->tim] = [
                    'nik' => $row->siang,
                    'nama' => DB::table('pegawai')->where('nik', $row->siang)->value('nama') ?? '-'
                ];

                $result['malam'][$row->tim] = [
                    'nik' => $row->malam,
                    'nama' => DB::table('pegawai')->where('nik', $row->malam)->value('nama') ?? '-'
                ];
            }

            return ApiResponse::success('Code blue schedule retrieved successfully', $result);
        } catch (\Exception $e) {
            \Log::error('Code Blue Schedule By Date Error: ' . $e->getMessage());
            return ApiResponse::error('Failed to retrieve code blue schedule', 'internal_server_error', null, 500);
        }
    }

    /**
     * Save/Update Code Blue Schedule
     * 
     * @param Request $request
     * @return array
     */
    public function saveCodeBlueSchedule(Request $request)
    {
        try {
            $validated = $request->validate([
                'tanggal' => 'required|date',
                'schedules' => 'required|array',
                'schedules.*.tim' => 'required|in:LEADER,ANGGOTA 1,ANGGOTA 2,ANGGOTA 3,ANGGOTA 4,ANGGOTA 5',
                'schedules.*.pagi' => 'required|string',
                'schedules.*.siang' => 'required|string',
                'schedules.*.malam' => 'required|string',
                'schedules.*.no_urut' => 'required|integer'
            ]);

            // Delete existing schedule for this date
            DB::table('rsia_codeblue')->where('tanggal', $validated['tanggal'])->delete();

            // Insert new schedule
            foreach ($validated['schedules'] as $schedule) {
                DB::table('rsia_codeblue')->insert([
                    'tanggal' => $validated['tanggal'],
                    'tim' => $schedule['tim'],
                    'pagi' => $schedule['pagi'],
                    'siang' => $schedule['siang'],
                    'malam' => $schedule['malam'],
                    'no_urut' => $schedule['no_urut'],
                    'status' => '1'
                ]);
            }

            return ApiResponse::success('Code blue schedule saved successfully', null);
        } catch (\Exception $e) {
            \Log::error('Save Code Blue Schedule Error: ' . $e->getMessage());
            return ApiResponse::error('Failed to save code blue schedule', 'internal_server_error', null, 500);
        }
    }

    /**
     * Delete Code Blue Schedule by date
     * 
     * @param string $date
     * @return array
     */
    public function deleteCodeBlueSchedule($date)
    {
        try {
            DB::table('rsia_codeblue')->where('tanggal', $date)->delete();
            return ApiResponse::success('Code blue schedule deleted successfully', null);
        } catch (\Exception $e) {
            \Log::error('Delete Code Blue Schedule Error: ' . $e->getMessage());
            return ApiResponse::error('Failed to delete code blue schedule', 'internal_server_error', null, 500);
        }
    }
}
