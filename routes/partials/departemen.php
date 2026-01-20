<?php 

use Orion\Facades\Orion;

Orion::resource('departemen', \App\Http\Controllers\Orion\DepartemenController::class)
    ->only(['index', 'search']);