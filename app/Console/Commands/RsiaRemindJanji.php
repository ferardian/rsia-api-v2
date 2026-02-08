<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Helpers\Notification\FirebaseCloudMessaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\AndroidConfig;

class RsiaRemindJanji extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rsia:remind-janji {--h1} {--h0}';

    /**
     * @var string
     */
    protected $description = 'Kirim pengingat jadwal pemeriksaan (H-1 dan H-0)';

    public function handle()
    {
        $h1 = $this->option('h1');
        $h0 = $this->option('h0');

        // Jika tidak ada opsi, jalankan keduanya (default)
        if (!$h1 && !$h0) {
            $h1 = true;
            $h0 = true;
        }

        if ($h1) {
            $this->info("Checking for H-1 appointment reminders...");
            $tomorrow = now()->addDay()->format('Y-m-d');
            $this->processReminders($tomorrow, 'reminder_h1', 'Pengingat Jadwal Besok');
        }

        if ($h0) {
            $this->info("Checking for H-0 appointment reminders...");
            $today = now()->format('Y-m-d');
            $this->processReminders($today, 'reminder_h0', 'Jadwal Pemeriksaan Hari Ini');
        }

        return 0;
    }

    private function processReminders($date, $type, $titleText)
    {
        // Query logic: join booking with pasien, dokter, poli, and reg_periksa (to get no_rawat)
        $appointments = DB::table('booking_registrasi')
            ->join('pasien', 'booking_registrasi.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->join('dokter', 'booking_registrasi.kd_dokter', '=', 'dokter.kd_dokter')
            ->join('poliklinik', 'booking_registrasi.kd_poli', '=', 'poliklinik.kd_poli')
            ->leftJoin('reg_periksa', function ($join) {
                $join->on('booking_registrasi.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
                    ->on('booking_registrasi.tanggal_periksa', '=', 'reg_periksa.tgl_registrasi');
            })
            ->where('booking_registrasi.tanggal_periksa', $date)
            ->whereIn('booking_registrasi.status', ['Belum', 'Terdaftar'])
            ->where(function($q) {
                $q->whereNull('reg_periksa.stts')
                  ->orWhere('reg_periksa.stts', 'Belum');
            })
            ->select(
                'booking_registrasi.*',
                'pasien.nm_pasien',
                'dokter.nm_dokter',
                'poliklinik.nm_poli',
                'reg_periksa.no_rawat'
            )
            ->get();

        if ($appointments->isEmpty()) {
            $this->info("No appointments found for $date.");
            return;
        }

        foreach ($appointments as $apt) {
            // Priority for no_rawat, fallback to generating a dummy from RM + Date if needed
            // But usually simulation shows reg_periksa is created.
            if (!$apt->no_rawat) {
                // If it's still just booking but no_rawat not created yet in reg_periksa,
                // we use a composite key for logs.
                $noRawat = $apt->no_rkm_medis . '-' . $apt->tanggal_periksa;
            } else {
                $noRawat = $apt->no_rawat;
            }

            // Check duplicate in rsia_notif_log
            $exists = DB::table('rsia_notif_log')
                ->where('no_rawat', $noRawat)
                ->where('type', $type)
                ->exists();

            if ($exists) continue;

            $this->sendNotification($apt, $type, $titleText, $noRawat);
        }
    }

    private function sendNotification($apt, $type, $titleText, $logKey)
    {
        $rmPasien = str_replace('/', '', $apt->no_rkm_medis);
        $topics = ["pasien_$rmPasien"];

        // Find Head of Family
        $master = DB::table('rsia_keluarga_pasien')
            ->where('no_rkm_medis_keluarga', $apt->no_rkm_medis)
            ->first();

        if ($master) {
            $rmMaster = str_replace('/', '', $master->no_rkm_medis_master);
            $topics[] = "pasien_$rmMaster";
        }

        $hour = now()->hour;
        $greeting = 'Selamat Malam';
        if ($hour >= 5 && $hour < 11) {
            $greeting = 'Selamat Pagi';
        } elseif ($hour >= 11 && $hour < 15) {
            $greeting = 'Selamat Siang';
        } elseif ($hour >= 15 && $hour < 18) {
            $greeting = 'Selamat Sore';
        }

        $jam = substr($apt->jam_reg ?? '00:00:00', 0, 5);
        
        if ($type == 'reminder_h1') {
            $body = "$greeting {$apt->nm_pasien}, mengingatkan besok pada tanggal {$apt->tanggal_periksa} pukul {$jam} memiliki jadwal pemeriksaan di {$apt->nm_poli}. Klik untuk detail.";
        } else {
            $body = "$greeting {$apt->nm_pasien}, mengingatkan hari ini memiliki jadwal pemeriksaan di {$apt->nm_poli} pukul {$jam}. Klik untuk detail.";
        }

        foreach ($topics as $topic) {
            try {
                $message = CloudMessage::withTarget('topic', $topic)
                    ->withData([
                        'type' => 'APPOINTMENT_REMINDER',
                        'topic' => $topic,
                        'nm_pasien' => $apt->nm_pasien,
                        'tgl_periksa' => $apt->tanggal_periksa,
                        'jam_periksa' => $jam,
                        'nm_poli' => $apt->nm_poli,
                        'nm_dokter' => $apt->nm_dokter,
                        'route' => '/home',
                        'title' => $titleText,
                        'body' => $body,
                        'image' => 'https://sim.rsIAaisyiyah.com/rsiapi-v2/public/logo-rsia-masakini.png',
                    ])
                    ->withAndroidConfig(AndroidConfig::fromArray([
                        'priority' => 'high',
                    ]));

                FirebaseCloudMessaging::send($message);
                $this->info("FCM Sent to $topic for {$apt->nm_pasien}");
            } catch (\Exception $e) {
                $this->error("Failed to send to $topic: " . $e->getMessage());
            }
        }

        // Insert into log
        DB::table('rsia_notif_log')->insertOrIgnore([
            'no_rawat' => $logKey,
            'type' => $type,
            'channel' => 'fcm',
            'sent_at' => now(),
            'payload' => json_encode($apt),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
