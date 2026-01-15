<?php

namespace App\Http\Controllers\Orion;

use App\Models\ResikoKerja;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class ResikoKerjaController extends Controller
{
    use DisableAuthorization;

    protected $model = ResikoKerja::class;

    public function filterableBy(): array
    {
        return ['kode_resiko', 'nama_resiko'];
    }

    public function searchableBy(): array
    {
        return ['kode_resiko', 'nama_resiko'];
    }

    public function sortableBy(): array
    {
        return ['kode_resiko', 'nama_resiko'];
    }
}
