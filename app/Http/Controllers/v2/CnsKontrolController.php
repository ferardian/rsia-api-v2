<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\BridgingSuratKontrolBpjs;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CnsKontrolController extends Controller
{
    /**
     * Ambil daftar surat kontrol BPJS yang belum didaftarkan kunjungan kontrolnya.
     * Filter: tgl_rencana >= hari ini, belum ada SEP kontrol (sep2).
     */
    public function index(Request $request)
    {
        $tglDari = $request->filled('tgl_dari')
            ? $request->tgl_dari
            : now()->format('Y-m-d');

        $tglSampai = $request->filled('tgl_sampai')
            ? $request->tgl_sampai
            : now()->addDays(7)->format('Y-m-d');

        $query = BridgingSuratKontrolBpjs::with([
            'sep:no_sep,nama_pasien,nomr,nmdpdjp,jnspelayanan',
            'sep.pasien:no_rkm_medis,nm_pasien,no_tlp',
        ])
        ->whereBetween('tgl_rencana', [$tglDari, $tglSampai])
        ->whereDoesntHave('sep2')
        ->where(function ($q) {
            $q->whereHas('sep', fn($s) => $s->where('jnspelayanan', '<>', '0'));
        });

        if ($request->filled('search')) {
            $kw = $request->search;
            $query->where(function ($q) use ($kw) {
                $q->where('no_surat', 'like', "%{$kw}%")
                  ->orWhere('no_sep', 'like', "%{$kw}%")
                  ->orWhereHas('sep', fn($s) => $s->where('nama_pasien', 'like', "%{$kw}%"));
            });
        }

        $data = $query->orderBy('tgl_rencana', 'asc')->get();

        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'OK'],
            'response' => $data,
        ]);
    }

    /**
     * Kirim WA pengingat jadwal kontrol ke pasien yang dipilih.
     */
    public function kirimNotifikasi(Request $request)
    {
        $request->validate([
            'no_surat' => 'required|array',
            'no_surat.*' => 'string',
        ]);

        // Tentukan sapaan berdasar jam
        $hour = (int) now()->setTimezone('Asia/Jakarta')->format('H');
        if ($hour >= 0 && $hour < 11)       $sapaan = 'pagi';
        elseif ($hour >= 11 && $hour < 15)  $sapaan = 'siang';
        elseif ($hour >= 15 && $hour < 19)  $sapaan = 'sore';
        else                                $sapaan = 'malam';

        $records = BridgingSuratKontrolBpjs::with([
            'sep:no_sep,nama_pasien,nomr,nmdpdjp,jnspelayanan',
            'sep.pasien:no_rkm_medis,nm_pasien,no_tlp',
        ])->whereIn('no_surat', $request->no_surat)->get();

        $count = 0;
        $skipped = 0;
        $latTime = now()->addSeconds(rand(25, 35));

        foreach ($records as $record) {
            $phone = preg_replace('/[\s\-]/', '', $record->sep?->pasien?->no_tlp ?? '');
            if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
                $skipped++;
                continue;
            }

            $nama       = $record->sep?->nama_pasien ?? 'Bapak/Ibu';
            $dokter     = $record->sep?->nmdpdjp ?? '-';
            $tglRencana = Carbon::parse($record->tgl_rencana)
                ->locale('id')->translatedFormat('l, d F Y');

            $message  = "Assalamualaikum wr. wb.\n";
            $message .= "RSIA AISYIYAH PEKAJANGAN\n\n";
            $message .= "Selamat {$sapaan} Bapak/Ibu *{$nama}* 🙏😊\n";
            $message .= "Mengingatkan untuk jadwal kontrol Anda:\n\n";
            $message .= "🗓 *Tanggal* : {$tglRencana}\n";
            $message .= "🩺 *Dokter*  : {$dokter}\n\n";
            $message .= "Apakah sudah melakukan pendaftaran untuk kontrol melalui *aplikasi Mobile JKN*?\n";
            $message .= "Mohon konfirmasi Bapak/Ibu.\n\n";
            $message .= "Apabila ada kendala saat mendaftar, Bapak/Ibu bisa hubungi kami kembali.\n\n";
            $message .= "Terima kasih\n";
            $message .= "Sehat dan Bahagia bersama kami 😊";

            \App\Jobs\SendWhatsApp::dispatch($phone, $message, 'pendaftaran')
                ->delay($latTime)
                ->onQueue('whatsapp');

            $latTime = $latTime->addSeconds(rand(25, 35));
            $count++;
        }

        return response()->json([
            'metadata' => [
                'code'    => 200,
                'message' => "Pengingat kontrol dikirim ke {$count} pasien"
                           . ($skipped > 0 ? " ({$skipped} dilewati karena nomor tidak valid)" : ''),
            ],
            'response' => ['count' => $count, 'skipped' => $skipped],
        ]);
    }
}
