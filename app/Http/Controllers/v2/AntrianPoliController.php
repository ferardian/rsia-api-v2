<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Poliklinik;
use App\Models\RegPeriksa;
use App\Models\JadwalPoli;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AntrianPoliController extends Controller
{
    public function antrianSummary()
    {
        try {
            // 1. Get current date and next 6 days
            $startDate = Carbon::today();
            
            // 2. Define specific clinics (Kandungan & Anak)
            $targetClinics = ['P001', 'P003', 'P007', 'P008', 'P009'];
            
            // 3. Prepare day mapping for database (WBI/SIMRS compatible)
            $indonesianDays = [
                'Sunday' => 'MINGGU',
                'Monday' => 'SENIN',
                'Tuesday' => 'SELASA',
                'Wednesday' => 'RABU',
                'Thursday' => 'KAMIS',
                'Friday' => 'JUMAT',
                'Saturday' => 'SABTU'
            ];

            $results = [];

            for ($i = 0; $i < 7; $i++) {
                $currentDate = $startDate->copy()->addDays($i);
                $dateString = $currentDate->format('Y-m-d');
                $englishDay = $currentDate->format('l');
                $dayName = $indonesianDays[$englishDay];

                $dayEntry = [
                    'tanggal' => $dateString,
                    'hari' => $dayName,
                    'poliklinik' => []
                ];

                // Get clinics that have schedules on this day among our target clinics
                $clinicsWithSchedule = Poliklinik::whereIn('kd_poli', $targetClinics)
                    ->whereHas('jadwal_dokter', function($q) use ($dayName) {
                        $q->where('hari_kerja', $dayName);
                    })
                    ->orderBy('nm_poli')
                    ->get(['kd_poli', 'nm_poli']);

                foreach ($clinicsWithSchedule as $clinic) {
                    $clinicEntry = [
                        'kd_poli' => $clinic->kd_poli,
                        'nm_poli' => $clinic->nm_poli,
                        'doctors' => []
                    ];

                    // Get doctors and their quotas for this clinic and day
                    $schedules = JadwalPoli::with('dokter:kd_dokter,nm_dokter')
                        ->where('kd_poli', $clinic->kd_poli)
                        ->where('hari_kerja', $dayName)
                        ->get(['kd_dokter', 'kuota', 'jam_mulai', 'jam_selesai']);

                    foreach ($schedules as $schedule) {
                        // Count registrations for this doctor, clinic, and date
                        $currentReg = RegPeriksa::where('kd_poli', $clinic->kd_poli)
                            ->where('kd_dokter', $schedule->kd_dokter)
                            ->where('tgl_registrasi', $dateString)
                            ->whereNotIn('stts', ['Batal'])
                            ->count();

                        $clinicEntry['doctors'][] = [
                            'kd_dokter' => $schedule->kd_dokter,
                            'nm_dokter' => $schedule->dokter->nm_dokter ?? 'Dokter Belum Ditentukan',
                            'jam_mulai' => $schedule->jam_mulai,
                            'jam_selesai' => $schedule->jam_selesai,
                            'kuota' => (int)$schedule->kuota,
                            'terisi' => $currentReg,
                            'tersedia' => max(0, (int)$schedule->kuota - $currentReg)
                        ];
                    }

                    if (!empty($clinicEntry['doctors'])) {
                        $dayEntry['poliklinik'][] = $clinicEntry;
                    }
                }

                $results[] = $dayEntry;
            }

            Log::info('AntrianPoliController: Successfully generated 7-day refined summary.');

            return ApiResponse::successWithData($results, 'Data antrian berhasil diambil');
        } catch (\Exception $e) {
            Log::error('AntrianPoliController Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return ApiResponse::error('Gagal mengambil data antrian: ' . $e->getMessage(), 'exception', null, 500);
        }
    }
    public function statusAntrian(Request $request)
    {
        $request->validate([
            'kd_poli' => 'required',
            'kd_dokter' => 'required',
        ]);
        
        $date = date('Y-m-d');
        
        // Logic Baru:
        // 1. Prioritas: Cari yang SEDANG dilayani (Berkunjung/Dirawat/Ada/Berkas Diterima)
        $active = RegPeriksa::where('kd_poli', $request->kd_poli)
            ->where('kd_dokter', $request->kd_dokter)
            ->where('tgl_registrasi', $date)
            ->whereIn('stts', ['Berkunjung', 'Dirawat', 'Ada', 'Berkas Diterima'])
            ->orderBy('no_reg', 'asc')
            ->first();

        if ($active) {
            $currentQueue = (int)$active->no_reg;
             $statusText = 'Sedang Diperiksa';
        } else {
            // 2. Fallback: Jika tidak ada yang aktif, ambil yang terakhir SUDAH selesai
            $lastServed = RegPeriksa::where('kd_poli', $request->kd_poli)
                ->where('kd_dokter', $request->kd_dokter)
                ->where('tgl_registrasi', $date)
                ->where('stts', 'Sudah')
                ->orderBy('no_reg', 'desc')
                ->first();
            
            $currentQueue = $lastServed ? (int)$lastServed->no_reg : 0;
            $statusText = $currentQueue > 0 ? 'Sedang Berlangsung' : 'Belum Dimulai';
        }
        
        $totalQueue = RegPeriksa::where('kd_poli', $request->kd_poli)
            ->where('kd_dokter', $request->kd_dokter)
            ->where('tgl_registrasi', $date)
            ->where('stts', '!=', 'Batal')
            ->count();

        // Hitung Sisa Antrian Spesifik (Jika parameter no_reg dikirim - atau global)
        $sisaAntrian = 0;
        if ($request->has('no_reg')) {
            $myReg = $request->no_reg;
            // UPDATE: Menghitung SEMUA yang statusnya 'Belum' (Total Antrian Belum Dipanggil)
            // Sesuai request: "bukan yang dibawah nomor saya, tapi semua"
            $sisaAntrian = RegPeriksa::where('kd_poli', $request->kd_poli)
                ->where('kd_dokter', $request->kd_dokter)
                ->where('tgl_registrasi', $date)
                // ->where('no_reg', '<', $myReg) // Logic lama (user specific) dihapus
                ->where('stts', 'Belum')
                ->count();
        }

        // DEBUG: Get first 5 records to see what statuses exist
        $samples = RegPeriksa::where('kd_poli', $request->kd_poli)
            ->where('kd_dokter', $request->kd_dokter)
            ->where('tgl_registrasi', $date)
            ->orderBy('no_reg', 'asc')
            ->limit(5)
            ->get(['no_reg', 'stts', 'no_rawat']);

        return ApiResponse::successWithData([
            'current_queue' => $currentQueue,
            'total_queue' => $totalQueue,
            'sisa_antrian' => $sisaAntrian,
            'status' => $statusText
        ], 'Status antrian berhasil diambil');
    }
}
