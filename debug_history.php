<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$no_rkm_medis = '079197';

echo "Checking for no_rkm_medis: $no_rkm_medis\n";

$pasien = \App\Models\Pasien::where('no_rkm_medis', $no_rkm_medis)->first();
if ($pasien) {
    echo "Pasien Found: " . $pasien->nm_pasien . "\n";
} else {
    echo "Pasien NOT Found with $no_rkm_medis\n";
    // Try with leading zero
    $no_rkm_medis_pad = str_pad($no_rkm_medis, 6, '0', STR_PAD_LEFT);
    echo "Trying with padded: $no_rkm_medis_pad\n";
    $pasien = \App\Models\Pasien::where('no_rkm_medis', $no_rkm_medis_pad)->first();
    if ($pasien) {
        echo "Pasien Found with padded: " . $pasien->nm_pasien . "\n";
    }
}

$count = \App\Models\RegPeriksa::where('no_rkm_medis', $no_rkm_medis)->count();
echo "RegPeriksa count for $no_rkm_medis: $count\n";

$no_rkm_medis_pad = str_pad($no_rkm_medis, 6, '0', STR_PAD_LEFT);
echo "Checking RegPeriksa for padded: $no_rkm_medis_pad\n";
$countPad = \App\Models\RegPeriksa::where('no_rkm_medis', $no_rkm_medis_pad)->count();
echo "RegPeriksa count for $no_rkm_medis_pad: $countPad\n";

if ($countPad > 0) {
    $data = \App\Models\RegPeriksa::where('no_rkm_medis', $no_rkm_medis_pad)->limit(5)->get();
} else {
    $data = \App\Models\RegPeriksa::where('no_rkm_medis', $no_rkm_medis)->limit(5)->get();
}
foreach ($data as $reg) {
    echo " - No Rawat: " . $reg->no_rawat . " | Tgl: " . $reg->tgl_registrasi . "\n";
}

// Check 'pemeriksaanRalan' relationship
if ($count > 0) {
    $first = \App\Models\RegPeriksa::where('no_rkm_medis', $no_rkm_medis)->first();
    echo "Checking relation pemeriksaanRalan for " . $first->no_rawat . "...\n";
    $ralan = $first->pemeriksaanRalan;
    echo "Relation count: " . $ralan->count() . "\n";
    foreach ($ralan as $r) {
        echo "Data Ralan (" . $r->jam_rawat . "): " . json_encode($r) . "\n";
    }
}
