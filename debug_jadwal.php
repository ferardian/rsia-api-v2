<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;
use App\Models\JadwalPegawai;
use App\Models\Pegawai;
use Carbon\Carbon;

echo "--- DEBUG START ---\n";

// 1. Inspect Table Columns
$columns = DB::getSchemaBuilder()->getColumnListing('temporary_presensi');
echo "Table 'temporary_presensi' Columns:\n";
print_r($columns);

// Stop execution here as we only need column listing
exit;

// 2. Check Data for a Sample NIK (or hardcoded ID if needed)
// Use the NIK from the previous context or a known valid one. 
// For now, let's list the first 5 records to see the structure.
$first5 = JadwalPegawai::take(5)->get();
echo "\nFirst 5 Records:\n";
foreach ($first5 as $row) {
    echo "ID: " . $row->id . ", Year: " . $row->tahun . ", Month: " . $row->bulan . "\n";
    // Check keys to see if they are H1, h1, etc.
    // print_r($row->toArray()); 
}

// 3. Specific Check for Today
$today = Carbon::today();
$currentMonth = $today->month;
$currentYear = $today->year;
$currentDay = $today->day;
echo "\nChecking for Year: $currentYear, Month: $currentMonth, Day: $currentDay\n";

// Try to find a specific record (assuming ID 1 for test, or just any record matching month/year)
$sample = JadwalPegawai::where('tahun', $currentYear)->where('bulan', $currentMonth)->first();

if ($sample) {
    echo "Found record for this month/year (ID: " . $sample->id . ")\n";
    $key = 'H' . $currentDay;
    $keyLower = 'h' . $currentDay;
    
    echo "Value for $key: " . ($sample->$key ?? 'UNDEFINED') . "\n";
    echo "Value for $keyLower: " . ($sample->$keyLower ?? 'UNDEFINED') . "\n";
    
    echo "Full attributes for this record:\n";
    print_r($sample->getAttributes());
} else {
    echo "No records found for Year $currentYear, Month $currentMonth\n";
}

echo "--- DEBUG END ---\n";
