<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Check Master Data
echo "--- Old Master Data (master_imunisasi) ---\n";
$oldMaster = \Illuminate\Support\Facades\DB::table('master_imunisasi')->get();
foreach ($oldMaster as $m) {
    echo "{$m->kode_imunisasi} => {$m->nama_imunisasi}\n";
}

echo "\n--- New Master Data (rsia_master_imunisasi) ---\n";
$newMaster = \Illuminate\Support\Facades\DB::table('rsia_master_imunisasi')->get();
foreach ($newMaster as $m) {
    echo "{$m->id} => {$m->nama_vaksin}\n";
}

// Check History Data
echo "\n--- Old History Data (riwayat_imunisasi) ---\n";
$oldHistory = \Illuminate\Support\Facades\DB::table('riwayat_imunisasi')->limit(5)->get();
foreach ($oldHistory as $h) {
    echo "RM: {$h->no_rkm_medis} - Kode: {$h->kode_imunisasi} - No: {$h->no_imunisasi}\n";
}

// Check if there is any way to link to date?
// checking if `reg_periksa` has relationship? No simple FK shown in DDL.
