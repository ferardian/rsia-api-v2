<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Helpers\Notification\FirebaseCloudMessaging;
use Kreait\Firebase\Messaging\CloudMessage;

class RsiaNotifResep extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rsia:notif-resep';

    /**
     * @var string
     */
    protected $description = 'Cek resep obat baru dan kirim data sync ke aplikasi mobile';

    public function handle()
    {
        $this->info("Checking for new prescriptions...");

        // 1. Ambil resep hari ini (atau kemarin) yang belum ada di tabel tracking
        // Kita prioritaskan ralan karena ranap biasanya obatnya dikelola perawat
        $resepBaru = DB::table('resep_obat as ro')
            ->join('reg_periksa as rp', 'ro.no_rawat', '=', 'rp.no_rawat')
            ->leftJoin('rsia_resep_obat_terkirim as track', 'ro.no_resep', '=', 'track.no_resep')
            ->whereNull('track.no_resep')
            ->where('ro.status', 'ralan')
            ->where('ro.tgl_perawatan', '>=', now()->subDays(1)->format('Y-m-d'))
            ->select('ro.no_resep', 'ro.no_rawat', 'rp.no_rkm_medis')
            ->get();

        if ($resepBaru->isEmpty()) {
            $this->info("No new prescriptions found.");
            return 0;
        }

        $this->info("Found " . $resepBaru->count() . " new prescriptions.");

        foreach ($resepBaru as $resep) {
            $sanitizedRm = str_replace('/', '', $resep->no_rkm_medis);
            $topic = "pasien_" . $sanitizedRm;
            
            try {
                // 1. Get Patient Name for easier denormalization
                $pasien = DB::table('pasien')->where('no_rkm_medis', $resep->no_rkm_medis)->first();
                $nmPasien = $pasien ? $pasien->nm_pasien : 'Pasien';

                // 2. Fetch Regular Medications
                $items = DB::table('resep_dokter')
                    ->where('no_resep', $resep->no_resep)
                    ->select('kode_brng', 'jml', 'aturan_pakai')
                    ->get();

                // 3. Fetch Compounded Medications (Racikan)
                $racikans = DB::table('resep_dokter_racikan')
                    ->where('no_resep', $resep->no_resep)
                    ->select('no_resep', 'nama_racik', 'aturan_pakai', 'kd_racik')
                    ->get();

                // 4. Group Medications by Schedule
                $schedules = []; // [datetime => [medication names]]
                
                // Process Regular Items
                foreach ($items as $item) {
                    $drug = DB::table('databarang')->where('kode_brng', $item->kode_brng)->first();
                    $nmObat = $drug ? $drug->nama_brng : $item->kode_brng;
                    $this->calculateSlots($resep->no_resep, $nmObat, $item->aturan_pakai, $item->jml, $schedules);
                }

                // Process Racikan Items
                foreach ($racikans as $racik) {
                    $this->calculateSlots($resep->no_resep, $racik->nama_racik, $racik->aturan_pakai, 10, $schedules); // Default 10 slots for racikan
                }

                // 5. Insert into rsia_medication_schedules
                foreach ($schedules as $time => $medications) {
                    DB::table('rsia_medication_schedules')->insertOrIgnore([
                        'no_resep' => $resep->no_resep,
                        'no_rkm_medis' => $resep->no_rkm_medis,
                        'nm_pasien' => $nmPasien,
                        'topic' => $topic,
                        'schedule_time' => $time,
                        'medication_summary' => implode(", ", array_unique($medications)),
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // 6. Build silent data message for Mobile Sync (Fallback)
                $message = CloudMessage::withTarget('topic', $topic)
                    ->withData([
                        'type' => 'SYNC_MEDICINE',
                        'no_resep' => $resep->no_resep,
                        'no_rawat' => $resep->no_rawat,
                        'no_rkm_medis' => $resep->no_rkm_medis,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]);

                FirebaseCloudMessaging::send($message);

                DB::table('rsia_resep_obat_terkirim')->insert([
                    'no_resep' => $resep->no_resep,
                    'created_at' => now()
                ]);

                $this->info("Scheduled and synced Resep: {$resep->no_resep}");
            } catch (\Exception $e) {
                $this->error("Error for {$resep->no_resep}: " . $e->getMessage());
            }
        }

        return 0;
    }

    private function calculateSlots($noResep, $nmObat, $aturan, $jml, &$schedules)
    {
        $aturan = strtolower($aturan);
        $freq = 0;
        $hours = [7]; // Default

        // 1. Parse "3x1", "3 x 1", etc.
        if (preg_match('/(\d+)\s*[xX]/i', $aturan, $matches)) {
            $freq = (int)$matches[1];
        } 
        // 2. Fallback for common text
        elseif (str_contains($aturan, 'tiga kali')) {
            $freq = 3;
        } elseif (str_contains($aturan, 'dua kali')) {
            $freq = 2;
        } elseif (str_contains($aturan, 'satu kali')) {
            $freq = 1;
        }

        if ($freq == 0) return;

        // Set hours based on frequency
        if ($freq == 1) $hours = [7];
        elseif ($freq == 2) $hours = [7, 19];
        elseif ($freq == 3) $hours = [7, 13, 19];
        elseif ($freq >= 4) $hours = [6, 12, 18, 0];

        // Estimate duration based on JML (quantity)
        $days = (int)ceil($jml / $freq);
        if ($days > 30) $days = 30; // Cap at 30 days
        if ($days < 1) $days = 1;

        $startDate = now();
        for ($i = 0; $i < $days; $i++) {
            foreach ($hours as $h) {
                // Offset calculation (30 mins before/after)
                $offset = 0;
                if (str_contains($aturan, 'sebelum makan')) $offset = -30;
                elseif (str_contains($aturan, 'sesudah makan')) $offset = 30;

                $dt = clone $startDate;
                $dt->addDays($i)->setHour($h)->setMinute($offset)->setSecond(0);
                
                // Don't schedule for times that have already passed today
                if ($dt->isPast()) continue;

                $timeKey = $dt->format('Y-m-d H:i:s');
                $schedules[$timeKey][] = $nmObat;
            }
        }
    }
}
