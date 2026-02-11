<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Helpers\Notification\FirebaseCloudMessaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;

class RsiaRemindObat extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rsia:remind-obat';

    /**
     * @var string
     */
    protected $description = 'Kirim pengingat obat terjadwal ke aplikasi mobile (High Priority)';

    public function handle()
    {
        $this->info("Checking for scheduled medication reminders...");

        // 1. Housekeeping (Cleanup data)
        try {
            // Hard Cleanup: Hapus SEMUA data > 30 hari (termasuk pending/failed)
            $cleanupHardDate = now()->subDays(30)->format('Y-m-d H:i:s');
            
            DB::table('rsia_medication_schedules')
                ->where('created_at', '<', $cleanupHardDate)
                ->delete();

            DB::table('rsia_resep_obat_terkirim')
                ->where('created_at', '<', $cleanupHardDate)
                ->delete();

            // Aggressive Cleanup: Hapus yang sudah SUKSES (sent) > 3 hari
            // Agar history tetap ada sebentar untuk audit, tapi tidak mengendap lama
            $cleanupSentDate = now()->subDays(3)->format('Y-m-d H:i:s');
            DB::table('rsia_medication_schedules')
                ->where('status', 'sent')
                ->where('sent_at', '<', $cleanupSentDate)
                ->delete();
                
            $this->info("Housekeeping: 30-day hard cleanup & 3-day 'sent' cleanup completed.");
        } catch (\Exception $e) {
            $this->error("Cleanup failed: " . $e->getMessage());
        }

        // 2. Ambil jadwal yang statusnya pending dan waktunya sudah tiba
        // Kita ambil batas 5 menit ke belakang untuk toleransi delay cron
        $now = now();
        $schedules = DB::table('rsia_medication_schedules')
            ->where('status', 'pending')
            ->where('schedule_time', '<=', $now->format('Y-m-d H:i:s'))
            ->get();

        if ($schedules->isEmpty()) {
            $this->info("No reminders to send at this time.");
            return 0;
        }

        // 3. Grouping by Topic (Pasien)
        // Agar jika pasien punya banyak resep di jam yang sama, cuma dapet 1 notif
        $groupedByTopic = $schedules->groupBy('topic');

        foreach ($groupedByTopic as $topic => $reminders) {
            $nmPasien = $reminders->first()->nm_pasien ?? 'Pasien';
            
            // Gabungkan semua nama obat
            $allMeds = [];
            foreach ($reminders as $r) {
                // medication_summary berisi daftar obat per resep (misal: "Paracetamol, Cefixime")
                $meds = explode(', ', $r->medication_summary);
                foreach ($meds as $m) {
                    $allMeds[] = trim($m);
                }
            }
            $medList = implode(', ', array_unique($allMeds));

            try {
                $hour = now()->hour;
                $greeting = 'Selamat Malam';
                if ($hour >= 5 && $hour < 11) {
                    $greeting = 'Selamat Pagi';
                } elseif ($hour >= 11 && $hour < 15) {
                    $greeting = 'Selamat Siang';
                } elseif ($hour >= 15 && $hour < 18) {
                    $greeting = 'Selamat Sore';
                }

                $title = "Waktunya Minum Obat 💊";
                $body = "$greeting $nmPasien, saatnya minum obat Anda. Klik untuk detail.";

                // 3. Build High Priority Message (Hybrid: Notification + Data)
                // 3. Build High Priority Message (Strict Data-Only Strategy)
                // Now that the user has granted "Unrestricted" battery permission,
                // we use Data-Only to force the Flutter background handler to run.
                // This allows the app to download the image and show the Large Icon (thumbnail).
                $logoUrl = "https://sim.rsiaaisyiyah.com/rsiapi-v2/public/app_icon_mobile.png";
                $message = CloudMessage::withTarget('topic', $topic)
                    ->withData([
                        'type' => 'MEDICINE_REMINDER_DIRECT',
                        'topic' => $topic,
                        'meds' => $medList,
                        'route' => '/home',
                        'title' => $title,
                        'body' => $body,
                        'image' => $logoUrl,
                    ])
                    ->withAndroidConfig(AndroidConfig::fromArray([
                        'priority' => 'high',
                        'ttl' => '3600s',
                    ]));

                // 4. Send via Firebase
                FirebaseCloudMessaging::send($message);

                // 5. Update Status
                $ids = $reminders->pluck('id')->toArray();
                DB::table('rsia_medication_schedules')
                    ->whereIn('id', $ids)
                    ->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                $this->info("Sent reminder to $topic: $medList");
            } catch (\Exception $e) {
                $this->error("Failed to send to $topic: " . $e->getMessage());
                
                $ids = $reminders->pluck('id')->toArray();
                DB::table('rsia_medication_schedules')
                    ->whereIn('id', $ids)
                    ->update([
                        'status' => 'failed',
                        'error_log' => $e->getMessage(),
                    ]);
            }
        }

        return 0;
    }
}
