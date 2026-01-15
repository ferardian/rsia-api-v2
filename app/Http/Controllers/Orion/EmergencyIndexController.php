<?php

namespace App\Http\Controllers\Orion;

use App\Models\EmergencyIndex;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class EmergencyIndexController extends Controller
{
    use DisableAuthorization;

    protected $model = EmergencyIndex::class;

    public function filterableBy(): array
    {
        return ['kode_emergency', 'nama_emergency'];
    }

    public function searchableBy(): array
    {
        return ['kode_emergency', 'nama_emergency'];
    }

    public function sortableBy(): array
    {
        return ['kode_emergency', 'nama_emergency'];
    }
}
