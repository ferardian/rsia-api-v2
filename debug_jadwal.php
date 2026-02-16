<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

echo "--- MENU LIST ---\n";
$menus = DB::table('rsia_menu')->get();
foreach ($menus as $m) {
    echo "ID: {$m->id_menu}, Parent: " . ($m->parent_id ?? 'root') . ", Name: {$m->nama_menu}, Route: {$m->route}\n";
}
echo "--- END ---\n";
