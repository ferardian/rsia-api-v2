<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$id = 177;
echo "Looking for Pegawai ID: $id\n";

$pegawai = Pegawai::where('id', $id)->first();

if ($pegawai) {
    echo "Found Pegawai:\n";
    echo "Nama: " . $pegawai->nama . "\n";
    echo "NIK: " . $pegawai->nik . "\n";
    
    // Reverse check
    $check = Pegawai::where('nik', $pegawai->nik)->first();
    echo "Reverse Check by NIK ({$pegawai->nik}): " . ($check ? "Found ID " . $check->id : "NOT FOUND") . "\n";
} else {
    echo "Pegawai ID $id not found.\n";
}
