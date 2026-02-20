<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\RegPeriksa;
use App\Models\JadwalPoli;
use App\Models\Dokter;
use App\Models\Poliklinik;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CnsDokterOffController extends Controller
{
    /**
     * Get registrasi data filtered by tgl_registrasi, kd_dokter, kd_poli
     */
    public function index(Request $request)
    {
        $query = RegPeriksa::with(['pasienSomeData', 'dokter', 'poliklinik'])
            ->where('status_lanjut', 'Ralan');

        if ($request->filled('tgl_registrasi')) {
            $query->where('tgl_registrasi', $request->tgl_registrasi);
        } else {
            $query->where('tgl_registrasi', now()->format('Y-m-d'));
        }

        if ($request->filled('kd_dokter')) {
            $query->where('kd_dokter', $request->kd_dokter);
        }

        if ($request->filled('kd_poli')) {
            $query->where('kd_poli', $request->kd_poli);
        }

        $data = $query->orderBy('no_rawat', 'desc')->get();

        // Add jadwal info for each record
        $data->transform(function ($item) {
            $hari = Carbon::parse($item->tgl_registrasi)->locale('id')->isoFormat('dddd');
            $jadwal = JadwalPoli::where('kd_dokter', $item->kd_dokter)
                ->where('kd_poli', $item->kd_poli)
                ->where('hari_kerja', Str::upper($hari))
                ->first();

            $item->jadwal_jam_mulai = $jadwal?->jam_mulai;
            $item->jadwal_jam_selesai = $jadwal?->jam_selesai;
            return $item;
        });

        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'OK'],
            'response' => $data
        ]);
    }

    /**
     * Get replacement schedule options for a specific dokter on a given date
     */
    public function jadwalPengganti(Request $request)
    {
        $request->validate([
            'kd_dokter' => 'required',
            'tanggal' => 'required|date',
        ]);

        $hari = Str::upper(Carbon::parse($request->tanggal)->locale('id')->isoFormat('dddd'));

        $jadwal = JadwalPoli::with('poliklinik')
            ->where('kd_dokter', $request->kd_dokter)
            ->where('hari_kerja', $hari)
            ->get();

        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'OK'],
            'response' => $jadwal
        ]);
    }

    /**
     * Send WhatsApp notification to selected patients about schedule change
     */
    public function kirimNotifikasi(Request $request)
    {
        $request->validate([
            'kd_dokter' => 'required|exists:dokter,kd_dokter',
            'tgl_registrasi' => 'required|date',
            'no_rkm_medis' => 'required|array',
            'no_rkm_medis.*' => 'string',
            'tanggal_pengganti' => 'nullable|date',
            'jam_mulai' => 'nullable',
            'jam_selesai' => 'nullable',
            'kd_poli_pengganti' => 'nullable',
        ]);

        $dokter = Dokter::find($request->kd_dokter);
        $namaDokter = $dokter?->nm_dokter ?? 'Dokter';

        $hariRegistrasi = Carbon::parse($request->tgl_registrasi)->translatedFormat('l, d F Y');

        // Build replacement schedule info
        $jadwalPenggantiText = '';
        if ($request->filled('tanggal_pengganti') && $request->filled('jam_mulai')) {
            $hariPengganti = Carbon::parse($request->tanggal_pengganti)->translatedFormat('l, d F Y');
            $jamMulai = Carbon::parse($request->jam_mulai)->format('H:i');
            $jamSelesai = $request->jam_selesai ? Carbon::parse($request->jam_selesai)->format('H:i') : 'Selesai';
            $jadwalPenggantiText = " pada hari {$hariPengganti} ({$jamMulai} - {$jamSelesai}).";
        } else {
            $jadwalPenggantiText = ' di hari lain.';
        }

        // Query patients with phone numbers
        $pasienList = \App\Models\Pasien::whereIn('no_rkm_medis', $request->no_rkm_medis)
            ->select('no_rkm_medis', 'nm_pasien', 'no_tlp')
            ->get();

        $count = 0;
        $skipped = 0;
        $latTime = now()->addSeconds(rand(25, 35));

        foreach ($pasienList as $pasien) {
            // Skip invalid phone numbers
            $phone = preg_replace('/[\s\-]/', '', $pasien->no_tlp ?? '');
            if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
                $skipped++;
                continue;
            }

            // Get jadwal info for this patient's registration
            $regPeriksa = RegPeriksa::where('no_rkm_medis', $pasien->no_rkm_medis)
                ->where('tgl_registrasi', $request->tgl_registrasi)
                ->where('kd_dokter', $request->kd_dokter)
                ->first();

            $hari = $regPeriksa ? Carbon::parse($regPeriksa->tgl_registrasi)->locale('id')->isoFormat('dddd') : '';
            $jadwal = $regPeriksa ? JadwalPoli::where('kd_dokter', $regPeriksa->kd_dokter)
                ->where('kd_poli', $regPeriksa->kd_poli)
                ->where('hari_kerja', Str::upper($hari))
                ->first() : null;

            $jamPraktik = ($jadwal?->jam_mulai && $jadwal?->jam_selesai)
                ? Carbon::parse($jadwal->jam_mulai)->format('H:i') . ' - ' . Carbon::parse($jadwal->jam_selesai)->format('H:i')
                : 'JAM PRAKTIK';

            // Generate message (matching CNS template)
            $message = "Assalamualaikum wr. wb.\n";
            $message .= "Selamat siang Bapak/Ibu 🙏😊\n\n";
            $message .= "Kepada pasien *{$namaDokter}* ";
            $message .= "hari {$hariRegistrasi}, poliklinik {$namaDokter} ({$jamPraktik}).\n";
            $message .= "*TUTUP PRAKTIK*.\n\n";
            $message .= "Pasien dapat mengatur ulang jadwal periksa{$jadwalPenggantiText}\n\n";
            $message .= "Kami sangat menghargai jika Bapak/Ibu dapat memberikan konfirmasi penerimaan informasi ini.\n";
            $message .= "Terima kasih atas perhatian dan pengertiannya 🙏\n\n";
            $message .= "*RSIA AISYIYAH PEKAJANGAN*\n-----\n";
            $message .= "pertanyaan dan informasi dapat disampaikan ke nomor 085640009934";

            \App\Jobs\SendWhatsApp::dispatch($pasien->no_tlp, $message, 'pendaftaran')
                ->delay($latTime)
                ->onQueue('whatsapp');

            $latTime = $latTime->addSeconds(rand(25, 35));
            $count++;
        }

        return response()->json([
            'metadata' => ['code' => 200, 'message' => "Notifikasi WhatsApp dikirim ke {$count} pasien" . ($skipped > 0 ? " ({$skipped} dilewati karena nomor tidak valid)" : '')],
            'response' => ['count' => $count, 'skipped' => $skipped]
        ]);
    }

    /**
     * Send WhatsApp notification about changed practice hours (Jam Poli)
     */
    public function kirimNotifikasiJamPoli(Request $request)
    {
        $request->validate([
            'kd_dokter' => 'required|exists:dokter,kd_dokter',
            'tgl_registrasi' => 'required|date',
            'no_rkm_medis' => 'required|array',
            'no_rkm_medis.*' => 'string',
            'jam_mulai_baru' => 'required',
            'jam_selesai_baru' => 'nullable',
        ]);

        $dokter = Dokter::find($request->kd_dokter);
        $namaDokter = $dokter?->nm_dokter ?? 'Dokter';
        $hariRegistrasi = Carbon::parse($request->tgl_registrasi)->translatedFormat('l, d F Y');

        $jamMulaiBaru = Carbon::parse($request->jam_mulai_baru)->format('H:i');
        $jamSelesaiBaru = $request->jam_selesai_baru
            ? Carbon::parse($request->jam_selesai_baru)->format('H:i')
            : 'Selesai';

        $pasienList = \App\Models\Pasien::whereIn('no_rkm_medis', $request->no_rkm_medis)
            ->select('no_rkm_medis', 'nm_pasien', 'no_tlp')
            ->get();

        $count = 0;
        $skipped = 0;
        $latTime = now()->addSeconds(rand(25, 35));

        foreach ($pasienList as $pasien) {
            $phone = preg_replace('/[\s\-]/', '', $pasien->no_tlp ?? '');
            if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
                $skipped++;
                continue;
            }

            // Get old jadwal for comparison
            $regPeriksa = RegPeriksa::where('no_rkm_medis', $pasien->no_rkm_medis)
                ->where('tgl_registrasi', $request->tgl_registrasi)
                ->where('kd_dokter', $request->kd_dokter)
                ->first();

            $hari = $regPeriksa ? Carbon::parse($regPeriksa->tgl_registrasi)->locale('id')->isoFormat('dddd') : '';
            $jadwalLama = $regPeriksa ? JadwalPoli::where('kd_dokter', $regPeriksa->kd_dokter)
                ->where('kd_poli', $regPeriksa->kd_poli)
                ->where('hari_kerja', Str::upper($hari))
                ->first() : null;

            $jadwalLamaText = ($jadwalLama?->jam_mulai && $jadwalLama?->jam_selesai)
                ? ' yang semula jam praktik ' . Carbon::parse($jadwalLama->jam_mulai)->format('H:i') . ' s/d ' . Carbon::parse($jadwalLama->jam_selesai)->format('H:i')
                : '';

            // Generate message (matching CNS Jam Poli template)
            $message = "Yth. Bpk/Ibu :\n";
            $message .= "*{$pasien->nm_pasien}*\n\n";
            $message .= "Kami informasikan adanya perubahan jam praktik untuk dokter *{$namaDokter}* pada *{$hariRegistrasi}*{$jadwalLamaText} menjadi jam *{$jamMulaiBaru} s/d {$jamSelesaiBaru}*.\n\n";
            $message .= "Mohon maaf atas ketidaknyamanan 🙏🙏.\n";
            $message .= "Terima kasih atas perhatian dan kerjasamanya.\n\n";
            $message .= "*RSIA AISYIYAH PEKAJANGAN*\n-----\n";
            $message .= "pertanyaan dan informasi dapat disampaikan ke nomor 085640009934";

            \App\Jobs\SendWhatsApp::dispatch($pasien->no_tlp, $message, 'pendaftaran')
                ->delay($latTime)
                ->onQueue('whatsapp');

            $latTime = $latTime->addSeconds(rand(25, 35));
            $count++;
        }

        return response()->json([
            'metadata' => ['code' => 200, 'message' => "Notifikasi WhatsApp dikirim ke {$count} pasien" . ($skipped > 0 ? " ({$skipped} dilewati karena nomor tidak valid)" : '')],
            'response' => ['count' => $count, 'skipped' => $skipped]
        ]);
    }

    /**
     * Send WhatsApp notification to confirm patient attendance
     */
    public function kirimNotifikasiKonfirmasiHadir(Request $request)
    {
        $request->validate([
            'kd_dokter' => 'required|exists:dokter,kd_dokter',
            'tgl_registrasi' => 'required|date',
            'no_rkm_medis' => 'required|array',
            'no_rkm_medis.*' => 'string',
        ]);

        $dokter = Dokter::find($request->kd_dokter);
        $namaDokter = $dokter?->nm_dokter ?? 'Dokter';

        // Tentukan sapaan berdasar jam saat ini
        $hour = (int) now()->setTimezone('Asia/Jakarta')->format('H');
        if ($hour >= 0 && $hour < 11) {
            $sapaan = 'pagi';
        } elseif ($hour >= 11 && $hour < 15) {
            $sapaan = 'siang';
        } elseif ($hour >= 15 && $hour < 19) {
            $sapaan = 'sore';
        } else {
            $sapaan = 'malam';
        }

        $pasienList = \App\Models\Pasien::whereIn('no_rkm_medis', $request->no_rkm_medis)
            ->select('no_rkm_medis', 'nm_pasien', 'no_tlp')
            ->get();

        // Ambil info poli dari salah satu registrasi pasien
        $sampleReg = RegPeriksa::where('tgl_registrasi', $request->tgl_registrasi)
            ->where('kd_dokter', $request->kd_dokter)
            ->with('poliklinik')
            ->first();
        $nmPoli = $sampleReg?->poliklinik?->nm_poli ?? 'Poliklinik';

        $count = 0;
        $skipped = 0;
        $latTime = now()->addSeconds(rand(25, 35));

        foreach ($pasienList as $pasien) {
            $phone = preg_replace('/[\s\-]/', '', $pasien->no_tlp ?? '');
            if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
                $skipped++;
                continue;
            }

            $message = "Assalamualaikum wr. wb.\n";
            $message .= "RSIA AISYIYAH PEKAJANGAN\n\n";
            $message .= "Selamat {$sapaan} *{$pasien->nm_pasien}* 🙏😊\n\n";
            $message .= "Menginformasikan untuk poli *{$nmPoli}* *{$namaDokter}* untuk hari ini sudah dimulai.\n";
            $message .= "Dimohon segera datang.\n\n";
            $message .= "Apakah pasien hadir periksa untuk hari ini?\n";
            $message .= "Kami tunggu balasan dari pasien.\n";
            $message .= "Apabila pasien berhalangan hadir dimohon untuk mengkonfirmasi kami.\n\n";
            $message .= "Terima kasih\n\n";
            $message .= "Sehat dan Bahagia bersama kami! 😊";

            \App\Jobs\SendWhatsApp::dispatch($pasien->no_tlp, $message, 'pendaftaran')
                ->delay($latTime)
                ->onQueue('whatsapp');

            $latTime = $latTime->addSeconds(rand(25, 35));
            $count++;
        }

        return response()->json([
            'metadata' => ['code' => 200, 'message' => "Notifikasi konfirmasi hadir dikirim ke {$count} pasien" . ($skipped > 0 ? " ({$skipped} dilewati karena nomor tidak valid)" : '')],
            'response' => ['count' => $count, 'skipped' => $skipped]
        ]);
    }

    /**
     * Get dokter list that have jadwal (for dropdown)
     */
    public function getDokter()
    {
        $dokter = Dokter::select('kd_dokter', 'nm_dokter', 'kd_sps')
            ->whereHas('jadwal')
            ->with('spesialis:kd_sps,nm_sps')
            ->orderBy('nm_dokter')
            ->get();

        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'OK'],
            'response' => $dokter
        ]);
    }

    /**
     * Get poliklinik list that have jadwal (for dropdown)
     */
    public function getPoliklinik()
    {
        $poli = Poliklinik::select('kd_poli', 'nm_poli')
            ->whereHas('jadwal_dokter')
            ->orderBy('nm_poli')
            ->get();

        return response()->json([
            'metadata' => ['code' => 200, 'message' => 'OK'],
            'response' => $poli
        ]);
    }
}
