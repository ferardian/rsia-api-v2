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
    /**
     * Get queue volume summary for the next 7 days for all active clinics.
     *
     * @return \Illuminate\Http\Response
     */
    public function antrianSummary()
    {
        try {
            // 1. Get current date and next 6 days
            $startDate = Carbon::today();
            $endDate = Carbon::today()->addDays(6);

            // 2. Get active clinics (excluding internal ones or inactive ones)
            $clinics = Poliklinik::where('status', '1')
                ->where('kd_poli', '!=', '-')
                ->orderBy('nm_poli')
                ->get(['kd_poli', 'nm_poli']);

            Log::info('AntrianPoliController: Found ' . $clinics->count() . ' active clinics.');

            // 3. Prepare result structure
            $results = [];
            $indonesianDays = [
                'Sunday' => 'MINGGU',
                'Monday' => 'SENIN',
                'Tuesday' => 'SELASA',
                'Wednesday' => 'RABU',
                'Thursday' => 'KAMIS',
                'Friday' => 'JUMAT',
                'Saturday' => 'SABTU'
            ];

            foreach ($clinics as $clinic) {
                $clinicData = [
                    'kd_poli' => $clinic->kd_poli,
                    'nm_poli' => $clinic->nm_poli,
                    'days' => []
                ];

                for ($i = 0; $i < 7; $i++) {
                    $currentDate = $startDate->copy()->addDays($i);
                    $dateString = $currentDate->format('Y-m-d');
                    // Force using English Day name as key for our mapping
                    $englishDay = $currentDate->format('l');
                    $dayName = $indonesianDays[$englishDay];

                    // Get total quota from jadwal for this clinic AND this specific day
                    $totalQuota = JadwalPoli::where('kd_poli', $clinic->kd_poli)
                        ->where('hari_kerja', $dayName)
                        ->sum('kuota');

                    // Get current registration count for this date
                    $currentReg = RegPeriksa::where('kd_poli', $clinic->kd_poli)
                        ->where('tgl_registrasi', $dateString)
                        ->whereNotIn('stts', ['Batal'])
                        ->count();

                    $clinicData['days'][] = [
                        'tanggal' => $dateString,
                        'hari' => $indonesianDays[$englishDay], // Keep it consistent
                        'kuota' => (int)$totalQuota,
                        'terisi' => $currentReg,
                        'tersedia' => max(0, (int)$totalQuota - $currentReg)
                    ];
                }

                $results[] = $clinicData;
            }

            Log::info('AntrianPoliController: Successfully generated ' . count($results) . ' clinic summaries.');

            return ApiResponse::successWithData($results, 'Data antrian berhasil diambil');
        } catch (\Exception $e) {
            Log::error('AntrianPoliController Error: ' . $e->getMessage());
            return ApiResponse::error('Gagal mengambil data antrian: ' . $e->getMessage(), 'exception', null, 500);
        }
    }
}
