<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SendPpraWaNotifications extends Command
{
    protected $signature = 'rsia:ppra-wa-notif';
    protected $description = 'Kirim notifikasi WA untuk resep antibiotik (PPRA) yang baru masuk';

    public function handle()
    {
        $this->info("Checking for new PPRA prescriptions...");

        // 1. Ambil resep obat ralan/ranap yang mengandung obat dalam mapping PPRA
        // Filter: Yang belum ada di rsia_ppra_notif_log
        $newPrescriptions = DB::table('resep_dokter as rd')
            ->join('resep_obat as ro', 'rd.no_resep', '=', 'ro.no_resep')
            ->join('reg_periksa as rp', 'ro.no_rawat', '=', 'rp.no_rawat')
            ->join('pasien as p', 'rp.no_rkm_medis', '=', 'p.no_rkm_medis')
            ->join('rsia_ppra_mapping_obat as map', 'rd.kode_brng', '=', 'map.kode_brng')
            ->join('databarang as db', 'rd.kode_brng', '=', 'db.kode_brng')
            ->leftJoin('rsia_ppra_notif_log as log', function($join) {
                $join->on('rd.no_resep', '=', 'log.no_resep')
                     ->on('rd.kode_brng', '=', 'log.kode_brng');
            })
            ->whereNull('log.no_resep')
            ->where('ro.tgl_perawatan', '>=', now()->subDays(2)->format('Y-m-d')) // Ambil 2 hari terakhir untuk safety buffer
            ->select(
                'rd.no_resep',
                'rd.kode_brng',
                'db.nama_brng',
                'rd.aturan_pakai',
                'rd.jml',
                'p.nm_pasien',
                'ro.no_rawat',
                'rp.no_rkm_medis'
            )
            ->get();

        if ($newPrescriptions->isEmpty()) {
            $this->info("No new PPRA prescriptions found.");
            return 0;
        }

        $this->info("Found " . $newPrescriptions->count() . " items to notify.");

        $n8nUrl = config('services.n8n.url') . '/webhook/ppra-outgoing-notif';

        // 2. Ambil daftar Apoteker/Farmasi di Tim PPRA untuk dikirimi WA
        $recipients = DB::table('rsia_tim_ppra')
            ->join('petugas', 'rsia_tim_ppra.nik', '=', 'petugas.nip')
            ->where(function($q) {
                $q->where('rsia_tim_ppra.jabatan', 'like', '%apoteker%')
                  ->orWhere('rsia_tim_ppra.jabatan', 'like', '%farmasi%');
            })
            ->whereNotNull('petugas.no_telp')
            ->select('petugas.no_telp', 'petugas.nama')
            ->get();

        if ($recipients->isEmpty()) {
            $this->error("No pharmacists found in rsia_tim_ppra to notify.");
            return 0;
        }

        foreach ($newPrescriptions as $item) {
            $this->info("Processing: {$item->no_resep} - {$item->nama_brng}");

            // Generate Unique 4-digit Short Code
            $shortCode = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            while (DB::table('rsia_ppra_notif_log')->where('short_code', $shortCode)->exists()) {
                $shortCode = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            }

            // Persiapan Pesan
            $messageText = "*NOTIFIKASI PPRA*\n\n" .
                           "Terdapat resep antibiotik baru:\n" .
                           "No. Resep: {$item->no_resep}\n" .
                           "Pasien: {$item->nm_pasien}\n" .
                           "Obat: {$item->nama_brng}\n" .
                           "Dosis: {$item->aturan_pakai}\n\n" .
                           "Balas WA ini dengan format:\n" .
                           "*ACC {$shortCode} [Catatan]*\n" .
                           "atau\n" .
                           "*TOLAK {$shortCode} [Alasan]*";

            $isLogged = false;
            foreach ($recipients as $recipient) {
                try {
                    // Normalize phone number (start with 62)
                    $phone = $recipient->no_telp;
                    if (str_starts_with($phone, '0')) {
                        $phone = '62' . substr($phone, 1);
                    }

                    $response = Http::post($n8nUrl, [
                        'no_resep' => $item->no_resep,
                        'kode_brng' => $item->kode_brng,
                        'short_code' => $shortCode,
                        'phone' => $phone,
                        'nm_pasien' => $item->nm_pasien,
                        'nama_obat' => $item->nama_brng,
                        'message_text' => $messageText,
                        'type' => 'OUTGOING_PPRA'
                    ]);

                    if ($response->successful() && !$isLogged) {
                        DB::table('rsia_ppra_notif_log')->insert([
                            'no_resep' => $item->no_resep,
                            'kode_brng' => $item->kode_brng,
                            'short_code' => $shortCode,
                            'tgl_notif' => now(),
                            'status_notif' => 'SENT'
                        ]);
                        $isLogged = true;
                        $this->info("Successfully notified recipients for: {$item->no_resep} with code {$shortCode}");
                    }
                } catch (\Exception $e) {
                    $this->error("Exception for {$item->no_resep} to {$recipient->nama}: " . $e->getMessage());
                }
            }
        }

        return 0;
    }
}
