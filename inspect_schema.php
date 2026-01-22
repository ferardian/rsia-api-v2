<?php
// inspect_schema.php
$columns = \Illuminate\Support\Facades\DB::select("DESCRIBE riwayat_barang_medis");
foreach ($columns as $col) {
    if (in_array($col->Field, ['tanggal', 'jam'])) {
        echo $col->Field . ": " . $col->Type . "\n";
    }
}
