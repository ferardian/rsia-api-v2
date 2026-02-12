<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use App\Models\TemporaryPresensi;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Ganti ID ini dengan ID dari data user (177)
$pegawaiId = 177; 

echo "Checking for Pegawai ID: $pegawaiId\n";

// 1. Cek Raw Data
$raw = DB::table('temporary_presensi')->where('id', $pegawaiId)->first();
echo "Raw Data:\n";
print_r($raw);

// 2. Cek dengan Model dan Carbon
$today = Carbon::today();
echo "\nToday (Carbon): " . $today->format('Y-m-d H:i:s') . "\n";

$model = TemporaryPresensi::where('id', $pegawaiId)
    ->whereDate('jam_datang', $today)
    ->first();

if ($model) {
    echo "\n[SUCCESS] Data found via Model!\n";
    echo "Jam Datang: " . $model->jam_datang->format('Y-m-d H:i:s') . "\n";
} else {
    echo "\n[FAILED] Data NOT found via Model query whereDate!\n";
    
    // Debugging whereDate issue
    $all = TemporaryPresensi::where('id', $pegawaiId)->get();
    echo "All records for this ID:\n";
    foreach ($all as $rec) {
        echo "- " . $rec->jam_datang->format('Y-m-d H:i:s') . "\n";
    }
}
