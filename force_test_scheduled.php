<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

// ==========================================
// CONFIGURATION
// ==========================================
$rm = "046064"; // GANTI DENGAN RM ANDA (CONTOH: 046064)
// ==========================================

$sanitizedRm = str_replace('/', '', $rm);
$topic = "pasien_" . $sanitizedRm;

echo "🧪 [FORCE SCHEDULE TEST] Starting test for topic: $topic\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// 1. Cek apakah ada jadwal pending untuk pasien ini
$pendingSchedules = DB::table('rsia_medication_schedules')
    ->where('topic', $topic)
    ->where('status', 'pending')
    ->get();

if ($pendingSchedules->isEmpty()) {
    echo "⚠️ TIDAK ADA JADWAL PENDING untuk topic: $topic\n";
    echo "🔄 Mencoba RESET jadwal yang sudah 'sent' atau 'failed' menjadi 'pending' agar bisa dites ulang...\n";
    
    $affected = DB::table('rsia_medication_schedules')
        ->where('topic', $topic)
        ->whereIn('status', ['sent', 'failed'])
        ->update(['status' => 'pending']);
        
    if ($affected > 0) {
        echo "✅ Berhasil me-reset $affected jadwal.\n";
        $pendingSchedules = DB::table('rsia_medication_schedules')
            ->where('topic', $topic)
            ->where('status', 'pending')
            ->get();
    } else {
        echo "❌ Tidak ada jadwal 'sent' untuk di-reset. Pastikan data ada di tabel 'rsia_medication_schedules'.\n";
        exit(1);
    }
}

echo "✅ Ditemukan " . $pendingSchedules->count() . " jadwal pending.\n";
echo "🔄 Mengubah waktu jadwal ke 'SAAT INI' agar bisa dieksekusi...\n";

// 2. Paksa pindah schedule_time ke masa lampau (agar picked up oleh command)
echo "⏰ Menyiapkan jadwal untuk: " . $pendingSchedules->first()->medication_summary . "\n";
echo "📌 Topic Target: $topic\n";

DB::table('rsia_medication_schedules')
    ->where('topic', $topic)
    ->where('status', 'pending')
    ->update(['schedule_time' => now()->subMinutes(1)->format('Y-m-d H:i:s')]);

echo "🚀 Menjalankan command 'rsia:remind-obat'...\n";

// 3. Jalankan command aslinya
Artisan::call('rsia:remind-obat');

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 Output Command:\n";
echo Artisan::output();
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🎉 Selesai. Cek HP Anda sekarang!\n";
