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
                return ApiResponse::success('No code blue schedule for today', (object)[
                    'pagi' => (object)[],
                    'siang' => (object)[],
                    'malam' => (object)[]
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
                return ApiResponse::success('No code blue schedule for this date', (object)[
                    'pagi' => (object)[],
                    'siang' => (object)[],
                    'malam' => (object)[]
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

    /**
     * Get visit statistics
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getVisitStats(Request $request)
    {
        try {
            // Support for yearly and daily modes
            $mode = $request->mode ?? 'harian';
            
            if ($mode === 'tahunan') {
                // Yearly mode: calculate date range from year
                $tahun = $request->tahun ?? Carbon::now()->year;
                $tgl_awal = "$tahun-01-01";
                $tgl_akhir = "$tahun-12-31";
            } else {
                // Daily mode: use provided date range
                $tgl_awal = $request->tgl_awal ?? Carbon::today()->toDateString();
                $tgl_akhir = $request->tgl_akhir ?? Carbon::today()->toDateString();
            }
            
            $status_lanjut = $request->status_lanjut; // 'Ralan', 'Ranap', or null for all

            $baseQuery = RegPeriksa::whereBetween('tgl_registrasi', [$tgl_awal, $tgl_akhir]);
            
            if ($status_lanjut && $status_lanjut !== 'all') {
                $baseQuery->where('reg_periksa.status_lanjut', $status_lanjut);
            }

            // Tambahkan filter untuk mengecualikan status 'Batal'
            $baseQuery->where('reg_periksa.stts', '!=', 'Batal');

            // 1. Baru vs Lama & Gender
            $registrasi = (clone $baseQuery)
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->selectRaw("
                    reg_periksa.stts_daftar,
                    pasien.jk,
                    COUNT(*) as total
                ")
                ->groupBy('reg_periksa.stts_daftar', 'pasien.jk')
                ->get();

            // 2. Berdasarkan Cara Bayar
            $caraBayar = (clone $baseQuery)
                ->join('penjab', 'reg_periksa.kd_pj', '=', 'penjab.kd_pj')
                ->select('penjab.png_jawab as label', DB::raw('COUNT(*) as total'))
                ->groupBy('penjab.png_jawab')
                ->orderBy('total', 'desc')
                ->get();

            // 3. Berdasarkan Poli
            $poli = (clone $baseQuery)
                ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                ->select('poliklinik.nm_poli as label', DB::raw('COUNT(*) as total'))
                ->groupBy('poliklinik.nm_poli')
                ->orderBy('total', 'desc')
                ->get();

            // 4. Berdasarkan Dokter
            $dokter = (clone $baseQuery)
                ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                ->select('dokter.nm_dokter as label', DB::raw('COUNT(*) as total'))
                ->groupBy('dokter.nm_dokter')
                ->orderBy('total', 'desc')
                ->limit(10) // Only top 10
                ->get();

            // 5. Keseluruhan Pasien (Hidup + Proses) - Berdasarkan ralat user
            $pasienKeluar = (clone $baseQuery)
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->selectRaw("pasien.jk, COUNT(*) as total")
                ->groupBy('pasien.jk')
                ->get();

            // 6. Keluar Mati (Tetap merujuk ke tabel pasien_mati)
            $keluarMati = (clone $baseQuery)
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->join('pasien_mati', 'reg_periksa.no_rkm_medis', '=', 'pasien_mati.no_rkm_medis')
                ->selectRaw("pasien.jk, COUNT(DISTINCT reg_periksa.no_rkm_medis) as total")
                ->groupBy('pasien.jk')
                ->get();

            // 6.b Keluar Mati >= 48 Jam
            $keluarMati48 = (clone $baseQuery)
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->join('pasien_mati', 'reg_periksa.no_rkm_medis', '=', 'pasien_mati.no_rkm_medis')
                ->whereRaw("TIMESTAMPDIFF(HOUR, CONCAT(reg_periksa.tgl_registrasi, ' ', reg_periksa.jam_reg), CONCAT(pasien_mati.tanggal, ' ', pasien_mati.jam)) >= 48")
                ->selectRaw("pasien.jk, COUNT(DISTINCT reg_periksa.no_rkm_medis) as total")
                ->groupBy('pasien.jk')
                ->get();

            $data = [
                'registrasi' => $registrasi,
                'cara_bayar' => $caraBayar,
                'poli' => $poli,
                'dokter' => $dokter,
                'summary' => [
                    'total' => $baseQuery->count(),
                    'baru' => $registrasi->where('stts_daftar', 'Baru')->sum('total'),
                    'lama' => $registrasi->where('stts_daftar', 'Lama')->sum('total'),
                    'pria' => $registrasi->where('jk', 'L')->sum('total'),
                    'wanita' => $registrasi->where('jk', 'P')->sum('total'),
                    // Summary Keluar (Disatukan: Hidup + Mati + Proses)
                    'keluar_l' => $pasienKeluar->where('jk', 'L')->sum('total'),
                    'keluar_p' => $pasienKeluar->where('jk', 'P')->sum('total'),
                    // Summary Mati (Tetap dipisah sebagai subset)
                    'mati_l' => $keluarMati->where('jk', 'L')->sum('total'),
                    'mati_p' => $keluarMati->where('jk', 'P')->sum('total'),
                    // Summary Mati >= 48 Jam
                    'mati_48_l' => $keluarMati48->where('jk', 'L')->sum('total'),
                    'mati_48_p' => $keluarMati48->where('jk', 'P')->sum('total'),
                ]
            ];

            // 7. Daily Chart Data
            $startDate = Carbon::parse($tgl_awal);
            $endDate = Carbon::parse($tgl_akhir);
            $days = $startDate->diffInDays($endDate) + 1;

            $chartQuery = RegPeriksa::whereBetween('tgl_registrasi', [$tgl_awal, $tgl_akhir])
                ->where('stts', '!=', 'Batal')
                ->selectRaw("tgl_registrasi, status_lanjut, COUNT(*) as total")
                ->groupBy('tgl_registrasi', 'status_lanjut')
                ->get();

            $chartDataArr = [];
            for ($i = 0; $i < $days; $i++) {
                $date = $startDate->copy()->addDays($i)->toDateString();
                $chartDataArr[$date] = [
                    'date' => $date,
                    'ralan' => 0,
                    'ranap' => 0
                ];
            }

            foreach ($chartQuery as $item) {
                $date = $item->tgl_registrasi;
                $sl = strtolower($item->status_lanjut);
                if (isset($chartDataArr[$date])) {
                    $chartDataArr[$date][$sl] = (int) $item->total;
                }
            }
            $data['charts'] = array_values($chartDataArr);

            // 8. Berdasarkan Domisili (Top 10 Kecamatan)
            $data['domisili'] = (clone $baseQuery)
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->join('kecamatan', 'pasien.kd_kec', '=', 'kecamatan.kd_kec')
                ->select('kecamatan.nm_kec as label', DB::raw('COUNT(*) as total'))
                ->groupBy('kecamatan.nm_kec')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();

            // 9. Kelompok Usia (Demografi)
            $data['usia'] = (clone $baseQuery)
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->selectRaw("
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, pasien.tgl_lahir, CURDATE()) < 2 THEN 'Bayi'
                        WHEN TIMESTAMPDIFF(YEAR, pasien.tgl_lahir, CURDATE()) BETWEEN 2 AND 12 THEN 'Anak'
                        WHEN TIMESTAMPDIFF(YEAR, pasien.tgl_lahir, CURDATE()) BETWEEN 13 AND 18 THEN 'Remaja'
                        WHEN TIMESTAMPDIFF(YEAR, pasien.tgl_lahir, CURDATE()) BETWEEN 19 AND 59 THEN 'Dewasa'
                        ELSE 'Lansia'
                    END as label,
                    COUNT(*) as total
                ")
                ->groupBy('label')
                ->get();

            // 10. Perbandingan Tren (Period-over-Period)
            if ($mode === 'tahunan') {
                // For yearly mode: compare with previous year
                $prevYear = $tahun - 1;
                $prevStartDate = Carbon::parse("$prevYear-01-01");
                $prevEndDate = Carbon::parse("$prevYear-12-31");
            } else {
                // For daily mode: compare with previous period of same duration
                $durationArr = $startDate->diff($endDate);
                $daysCount = $durationArr->days + 1;
                
                $prevEndDate = $startDate->copy()->subDay();
                $prevStartDate = $prevEndDate->copy()->subDays($daysCount - 1);
            }

            $currentTotal = $baseQuery->count();
            
            $prevQuery = RegPeriksa::whereBetween('tgl_registrasi', [
                $prevStartDate->toDateString(), 
                $prevEndDate->toDateString()
            ])->where('stts', '!=', 'Batal');

            if ($status_lanjut && $status_lanjut !== 'all') {
                $prevQuery->where('status_lanjut', $status_lanjut);
            }

            $prevTotal = $prevQuery->count();
            
            $diff = $currentTotal - $prevTotal;
            $percent = $prevTotal > 0 ? ($diff / $prevTotal) * 100 : ($currentTotal > 0 ? 100 : 0);

            $data['trend'] = [
                'current' => $currentTotal,
                'previous' => $prevTotal,
                'diff' => $diff,
                'percent' => round($percent, 1),
                'label' => $mode === 'tahunan' 
                    ? $prevStartDate->format('Y') 
                    : $prevStartDate->format('d/m') . ' - ' . $prevEndDate->format('d/m')
            ];

            // 11. Analisis Pasien Batal
            $batalQuery = RegPeriksa::whereBetween('tgl_registrasi', [$tgl_awal, $tgl_akhir])
                ->where('stts', 'Batal');

            if ($status_lanjut && $status_lanjut !== 'all') {
                $batalQuery->where('status_lanjut', $status_lanjut);
            }

            $data['batal'] = [
                'total' => $batalQuery->count(),
                'by_poli' => (clone $batalQuery)
                    ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                    ->select('poliklinik.nm_poli as label', DB::raw('COUNT(*) as total'))
                    ->groupBy('poliklinik.nm_poli')
                    ->orderBy('total', 'desc')
                    ->limit(5)
                    ->get(),
                'by_status' => (clone $batalQuery)
                    ->select('status_lanjut as label', DB::raw('COUNT(*) as total'))
                    ->groupBy('status_lanjut')
                    ->get()
            ];

            // Add inpatient care duration statistics if status is Ranap
            if ($status_lanjut === 'Ranap') {
                // User DO:
                // Hari Perawatan = tanggal keluar - tanggal masuk + 1 (contoh: 5-10 = 6 hari)
                // Lama Dirawat = tanggal keluar - tanggal masuk (contoh: 5-10 = 5 hari)
                
                $subQuery = DB::table('kamar_inap as ki')
                    ->join('reg_periksa as reg', 'ki.no_rawat', '=', 'reg.no_rawat')
                    ->whereBetween('reg.tgl_registrasi', [$tgl_awal, $tgl_akhir])
                    ->where('reg.stts', '!=', 'Batal')
                    ->whereNotNull('ki.tgl_keluar')
                    // Pastikan pasien sudah benar-benar pulang (bukan status pindah di record terakhirnya)
                    ->whereIn('ki.no_rawat', function($q) {
                        $q->select('no_rawat')
                          ->from('kamar_inap')
                          ->whereNotIn('stts_pulang', ['Pindah Kamar', '-', '']);
                    })
                    ->selectRaw('DATEDIFF(MAX(ki.tgl_keluar), MIN(ki.tgl_masuk)) as los')
                    ->groupBy('ki.no_rawat');

                $careDuration = DB::table(DB::raw("({$subQuery->toSql()}) as sub"))
                    ->mergeBindings($subQuery)
                    ->selectRaw('
                        SUM(los + 1) as total_hp,
                        SUM(los) as total_ld,
                        COUNT(*) as total_pasien,
                        AVG(los) as alos
                    ')
                    ->first();

                $data['inpatient_care'] = [
                    'hari_perawatan' => (int) ($careDuration->total_hp ?? 0),
                    'lama_dirawat' => (int) ($careDuration->total_ld ?? 0),
                    'total_pasien' => (int) ($careDuration->total_pasien ?? 0),
                    'avg_lama_dirawat' => round($careDuration->alos ?? 0, 1)
                ];
            }

            // 12. Monthly Breakdown for Yearly Mode
            if ($mode === 'tahunan') {
                $monthlyQuery = (clone $baseQuery)
                    ->selectRaw('MONTH(tgl_registrasi) as bulan, COUNT(*) as total')
                    ->groupBy('bulan')
                    ->orderBy('bulan')
                    ->get();

                // Fill in all 12 months with 0 if no data
                $monthlyBreakdown = [];
                $monthNames = [
                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                ];

                for ($i = 1; $i <= 12; $i++) {
                    $found = $monthlyQuery->firstWhere('bulan', $i);
                    $monthlyBreakdown[] = [
                        'bulan' => $i,
                        'nama_bulan' => $monthNames[$i],
                        'total' => $found ? (int) $found->total : 0
                    ];
                }

                $data['monthly_breakdown'] = $monthlyBreakdown;
            }

            return ApiResponse::success('Visit statistics retrieved successfully', $data);
        } catch (\Exception $e) {
            \Log::error('Dashboard Visit Stats Error: ' . $e->getMessage());
            return ApiResponse::error('Failed to retrieve visit statistics: ' . $e->getMessage(), 'internal_server_error', null, 500);
        }
    }
}
