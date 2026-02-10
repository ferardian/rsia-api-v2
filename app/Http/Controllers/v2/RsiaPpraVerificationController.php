<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Helpers\ApiResponse;

class RsiaPpraVerificationController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'no_resep' => 'required|string',
            'kode_brng' => 'required|string',
            'aturan_pakai' => 'nullable|string',
            'keterangan' => 'nullable|string',
        ]);

        $data = [
            'no_resep' => $request->no_resep,
            'kode_brng' => $request->kode_brng,
            'aturan_pakai' => $request->aturan_pakai,
            'keterangan' => $request->keterangan,
            'nik_petugas' => auth()->user()->username ?? '-',
            'updated_at' => now(),
        ];

        DB::table('rsia_ppra_resep_verifikasi')->updateOrInsert(
            ['no_resep' => $request->no_resep, 'kode_brng' => $request->kode_brng],
            $data
        );

        return ApiResponse::success('Data verifikasi PPRA berhasil disimpan', $data);
    }

    public function telaah(Request $request)
    {
        $request->validate([
            'no_resep' => 'required|string',
            'kode_brng' => 'required|string',
            'status_telaah' => 'required|string', // SESUAI, TIDAK SESUAI
            'catatan_telaah' => 'nullable|string',
        ]);

        $data = [
            'petugas_telaah' => auth()->user()->username ?? '-',
            'status_telaah' => $request->status_telaah,
            'catatan_telaah' => $request->catatan_telaah,
            'tgl_telaah' => now(),
            'updated_at' => now(),
        ];

        DB::table('rsia_ppra_resep_verifikasi')->updateOrInsert(
            ['no_resep' => $request->no_resep, 'kode_brng' => $request->kode_brng],
            $data
        );

        return ApiResponse::success('Telaah apoteker berhasil disimpan', $data);
    }

    public function approve(Request $request)
    {
        $request->validate([
            'no_resep' => 'required|string',
            'kode_brng' => 'required|string',
            'status_persetujuan' => 'required|string', // ACC, REJECT
            'catatan_persetujuan' => 'nullable|string',
        ]);

        $data = [
            'petugas_persetujuan' => auth()->user()->username ?? '-',
            'status_persetujuan' => $request->status_persetujuan,
            'catatan_persetujuan' => $request->catatan_persetujuan,
            'tgl_persetujuan' => now(),
            'updated_at' => now(),
        ];

        DB::table('rsia_ppra_resep_verifikasi')->updateOrInsert(
            ['no_resep' => $request->no_resep, 'kode_brng' => $request->kode_brng],
            $data
        );

        return ApiResponse::success('Persetujuan ketua PPRA berhasil disimpan', $data);
    }

    public function verifyWa(Request $request)
    {
        $request->validate([
            'command' => 'required|string', // ACC, TOLAK
            'code' => 'required|string',    // 4-digit short_code
            'comment' => 'nullable|string',
            'sender' => 'required|string',  // WA Number (e.g. 628123...)
        ]);

        // 1. Map phone number to employee
        $cleanSender = preg_replace('/[^0-9]/', '', $request->sender);
        // Usually ends with the actual number (ignoring 62/0/+)
        $searchPhone = substr($cleanSender, -10); 

        $petugas = DB::table('petugas')
            ->join('rsia_tim_ppra', 'petugas.nip', '=', 'rsia_tim_ppra.nik')
            ->where('petugas.no_telp', 'like', '%' . $searchPhone)
            ->select('petugas.nip', 'rsia_tim_ppra.jabatan', 'rsia_tim_ppra.role')
            ->first();

        if (!$petugas) {
            return ApiResponse::error('Nomor pengirim tidak terdaftar sebagai Tim PPRA', 403);
        }

        // 2. Find prescription by short_code
        $log = DB::table('rsia_ppra_notif_log')
            ->where('short_code', $request->code)
            ->first();

        if (!$log) {
            return ApiResponse::error('Kode verifikasi tidak valid atau sudah kadaluarsa', 404);
        }

        $data = [
            'updated_at' => now(),
        ];

        // 3. Logic based on role and command
        $status_msg = "";
        if (
            str_contains(strtolower($petugas->jabatan), 'apoteker') || 
            str_contains(strtolower($petugas->jabatan), 'farmasi') ||
            str_contains(strtolower($petugas->role), 'apoteker') || 
            str_contains(strtolower($petugas->role), 'farmasi')
        ) {
            // Pharmacist Review (Telaah)
            $data['petugas_telaah'] = $petugas->nip;
            $data['status_telaah'] = strtoupper($request->command) == 'ACC' ? 'SESUAI' : 'TIDAK SESUAI';
            $data['catatan_telaah'] = $request->comment;
            $data['tgl_telaah'] = now();
            $status_msg = "Telaah apoteker berhasil diperbarui via WA";
        } else {
            // Chairman Approval
            $data['petugas_persetujuan'] = $petugas->nip;
            $data['status_persetujuan'] = strtoupper($request->command) == 'ACC' ? 'ACC' : 'REJECT';
            $data['catatan_persetujuan'] = $request->comment;
            $data['tgl_persetujuan'] = now();
            $status_msg = "Persetujuan ketua berhasil diperbarui via WA";
        }

        DB::table('rsia_ppra_resep_verifikasi')->updateOrInsert(
            ['no_resep' => $log->no_resep, 'kode_brng' => $log->kode_brng],
            $data
        );

        // 4. If Pharmacist ACCs -> Forward to Ketua PPRA
        if (isset($data['status_telaah']) && $data['status_telaah'] == 'SESUAI') {
            $this->notifyKetua($log->no_resep, $log->kode_brng, $log->short_code, $petugas->nip);
        }

        return ApiResponse::success($status_msg, [
            'no_resep' => $log->no_resep,
            'kode_brng' => $log->kode_brng,
            'role' => $petugas->jabatan,
            'status' => strtoupper($request->command)
        ]);
    }

    protected function notifyKetua($no_resep, $kode_brng, $shortCode, $nipApoteker)
    {
        // 1. Get Ketua PPRA details
        $ketua = DB::table('rsia_tim_ppra')
            ->join('petugas', 'rsia_tim_ppra.nik', '=', 'petugas.nip')
            ->where('rsia_tim_ppra.jabatan', 'Ketua')
            ->select('petugas.no_telp', 'petugas.nama')
            ->first();

        if (!$ketua || !$ketua->no_telp) return;

        // 2. Get Apoteker name
        $apoteker = DB::table('petugas')->where('nip', $nipApoteker)->value('nama');

        // 3. Get Prescription & Patient Details
        $item = DB::table('resep_obat as ro')
            ->join('reg_periksa as rp', 'ro.no_rawat', '=', 'rp.no_rawat')
            ->join('pasien as p', 'rp.no_rkm_medis', '=', 'p.no_rkm_medis')
            ->join('detail_pemberian_obat as dpo', function($join) {
                $join->on('ro.no_rawat', '=', 'dpo.no_rawat')
                     ->on('ro.tgl_perawatan', '=', 'dpo.tgl_perawatan')
                     ->on('ro.jam', '=', 'dpo.jam');
            })
            ->join('databarang as db', 'dpo.kode_brng', '=', 'db.kode_brng')
            ->leftJoin('resep_dokter as rd', function($join) {
                $join->on('ro.no_resep', '=', 'rd.no_resep')
                     ->on('dpo.kode_brng', '=', 'rd.kode_brng');
            })
            ->leftJoin(DB::raw('(SELECT no_rawat, berat FROM pemeriksaan_ranap WHERE berat IS NOT NULL AND berat > 0 ORDER BY tgl_perawatan DESC, jam_rawat DESC) as pr'), function($join) {
                $join->on('ro.no_rawat', '=', 'pr.no_rawat');
            })
            ->where('ro.no_resep', $no_resep)
            ->where('dpo.kode_brng', $kode_brng)
            ->select(
                'ro.no_resep',
                'db.nama_brng',
                DB::raw('COALESCE(rd.aturan_pakai, "-") as aturan_pakai'),
                'p.nm_pasien',
                'p.tgl_lahir',
                DB::raw('COALESCE(pr.berat, 0) as berat_badan')
            )
            ->first();

        if (!$item) return;

        // Calculate age
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

        // Prepare Message
        $messageText = "🏥 *APPROVAL PPRA (KETUA) - RSIA AISYIYAH*\n" .
                       "━━━━━━━━━━━━━━━━━━━\n" .
                       "✅ *Hasil Telaah Apoteker:*\n" .
                       "Resep ini telah ditelaah oleh *{$apoteker}* dengan hasil: *SESUAI*.\n\n" .
                       "💊 *Detail Resep Antibiotik:*\n" .
                       "• Pasien: *{$item->nm_pasien}*\n" .
                       "• Usia: {$usia} | BB: {$beratBadan}\n" .
                       "• Obat: _{$item->nama_brng}_\n" .
                       "• Dosis: *{$item->aturan_pakai}*\n" .
                       "━━━━━━━━━━━━━━━━━━━\n" .
                       "📱 *Konfirmasi Ketua:*\n" .
                       "Silakan balas pesan ini dengan kode:\n\n" .
                       "✅ *ACC {$shortCode} [Catatan]*\n" .
                       "❌ *TOLAK {$shortCode} [Alasan]*\n\n" .
                       "_Pesan ini diteruskan otomatis setelah telaah Apoteker._";

        // Send WA
        $n8nUrl = config('services.n8n.url') . '/webhook/ppra-outgoing-notif';
        $phone = $ketua->no_telp;
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        Http::post($n8nUrl, [
            'no_resep' => $no_resep,
            'kode_brng' => $kode_brng,
            'short_code' => $shortCode,
            'phone' => $phone,
            'nm_pasien' => $item->nm_pasien,
            'nama_obat' => $item->nama_brng,
            'message_text' => $messageText,
            'type' => 'OUTGOING_PPRA'
        ]);
    }

    public function show(Request $request)
    {
        $no_resep = $request->query('no_resep');
        $kode_brng = $request->query('kode_brng');

        $data = DB::table('rsia_ppra_resep_verifikasi')
            ->where('no_resep', $no_resep)
            ->where('kode_brng', $kode_brng)
            ->first();

        return ApiResponse::success('Data verifikasi PPRA berhasil diambil', $data);
    }
}
