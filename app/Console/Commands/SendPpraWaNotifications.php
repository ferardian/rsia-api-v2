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
        // Filter: Hanya untuk pasien dengan dokter spesialis anak (S0003)
        $newPrescriptions = DB::table('resep_obat as ro')
            ->join('reg_periksa as rp', 'ro.no_rawat', '=', 'rp.no_rawat')
            ->join('pasien as p', 'rp.no_rkm_medis', '=', 'p.no_rkm_medis')
            ->join('dokter as d', 'rp.kd_dokter', '=', 'd.kd_dokter')
            ->join('detail_pemberian_obat as dpo', function($join) {
                $join->on('ro.no_rawat', '=', 'dpo.no_rawat')
                     ->on('ro.tgl_perawatan', '=', 'dpo.tgl_perawatan')
                     ->on('ro.jam', '=', 'dpo.jam');
            })
            ->join('rsia_ppra_mapping_obat as map', 'dpo.kode_brng', '=', 'map.kode_brng')
            ->join('databarang as db', 'dpo.kode_brng', '=', 'db.kode_brng')
            ->leftJoin('resep_dokter as rd', function($join) {
                $join->on('ro.no_resep', '=', 'rd.no_resep')
                     ->on('dpo.kode_brng', '=', 'rd.kode_brng');
            })
            ->leftJoin('rsia_ppra_notif_log as log', function($join) {
                $join->on('ro.no_resep', '=', 'log.no_resep')
                     ->on('dpo.kode_brng', '=', 'log.kode_brng');
            })
            // Get latest weight from pemeriksaan_ranap
            ->leftJoin(DB::raw('(SELECT no_rawat, berat FROM pemeriksaan_ranap WHERE berat IS NOT NULL AND berat > 0 ORDER BY tgl_perawatan DESC, jam_rawat DESC) as pr'), function($join) {
                $join->on('ro.no_rawat', '=', 'pr.no_rawat');
            })
            ->whereNull('log.no_resep')
            // � Filter 1 jam terakhir untuk catch semua resep yang belum terkirim
            ->where('ro.tgl_perawatan', '>=', now()->subHour()->toDateTimeString())
            ->where('ro.status', 'like', 'ranap%')
            // 🩺 Filter: Hanya dokter spesialis anak
            ->where('d.kd_sps', 'S0003')
            ->select(
                'ro.no_resep',
                'dpo.kode_brng',
                'db.nama_brng',
                DB::raw('COALESCE(rd.aturan_pakai, "-") as aturan_pakai'),
                'dpo.jml',
                'p.nm_pasien',
                'p.tgl_lahir',
                DB::raw('COALESCE(pr.berat, 0) as berat_badan'),
                'ro.no_rawat',
                'rp.no_rkm_medis'
            )
            ->groupBy(
                'ro.no_resep', 
                'dpo.kode_brng', 
                'db.nama_brng', 
                'rd.aturan_pakai', 
                'dpo.jml', 
                'p.nm_pasien',
                'p.tgl_lahir',
                'pr.berat',
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
                  ->orWhere('rsia_tim_ppra.jabatan', 'like', '%farmasi%')
                  ->orWhere('rsia_tim_ppra.role', 'like', '%apoteker%')
                  ->orWhere('rsia_tim_ppra.role', 'like', '%farmasi%');
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

            // Calculate age from birth date
            $usia = '-';
            if ($item->tgl_lahir && $item->tgl_lahir != '0000-00-00') {
                $birthDate = Carbon::parse($item->tgl_lahir);
                $years = $birthDate->diffInYears(now());
                $months = $birthDate->copy()->addYears($years)->diffInMonths(now());
                $days = $birthDate->copy()->addYears($years)->addMonths($months)->diffInDays(now());
                
                if ($years > 0) {
                    $usia = "{$years} tahun";
                    if ($months > 0) $usia .= " {$months} bulan";
                } elseif ($months > 0) {
                    $usia = "{$months} bulan";
                    if ($days > 0) $usia .= " {$days} hari";
                } else {
                    $usia = "{$days} hari";
                }
            }
            
            $beratBadan = $item->berat_badan > 0 ? number_format((float)$item->berat_badan, 1) . ' kg' : '-';

            // Persiapan Pesan (More Compact & Professional)
            $messageText = "🏥 *NOTIFIKASI PPRA - RSIA AISYIYAH*\n" .
                           "━━━━━━━━━━━━━━━━━━━\n" .
                           "💊 *Detail Resep Antibiotik:*\n" .
                           "• No. Resep: `{$item->no_resep}`\n" .
                           "• Pasien: *{$item->nm_pasien}*\n" .
                           "• Usia: {$usia}\n" .
                           "• Berat Badan: {$beratBadan}\n" .
                           "• Obat: _{$item->nama_brng}_\n" .
                           "• Dosis: *{$item->aturan_pakai}*\n" .
                           "━━━━━━━━━━━━━━━━━━━\n" .
                           "📱 *Konfirmasi Cepat:*\n" .
                           "Silakan balas pesan ini dengan kode:\n\n" .
                           "✅ *ACC {$shortCode} [Catatan]*\n" .
                           "❌ *TOLAK {$shortCode} [Alasan]*\n\n" .
                           "_Pesan ini dikirim otomatis oleh sistem PPRA RSIA._";

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
                        
                        // Tambah jeda 2 detik per pesan untuk menghindari spam/blokir
                        sleep(2);
                    }
                } catch (\Exception $e) {
                    $this->error("Exception for {$item->no_resep} to {$recipient->nama}: " . $e->getMessage());
                }
            }
        }

        return 0;
    }
}
