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
                // 2. Build silent data message
                $message = CloudMessage::withTarget('topic', $topic)
                    ->withData([
                        'type' => 'SYNC_MEDICINE',
                        'no_resep' => $resep->no_resep,
                        'no_rawat' => $resep->no_rawat,
                        'no_rkm_medis' => $resep->no_rkm_medis,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]);

                // 3. Send via Firebase
                FirebaseCloudMessaging::send($message);

                // 4. Record as sent
                DB::table('rsia_resep_obat_terkirim')->insert([
                    'no_resep' => $resep->no_resep,
                    'created_at' => now()
                ]);

                $this->info("Sent sync signal for Resep: {$resep->no_resep} to Topic: {$topic}");
            } catch (\Exception $e) {
                $this->error("Failed to send notification for {$resep->no_resep}: " . $e->getMessage());
            }
        }

        return 0;
    }
}
