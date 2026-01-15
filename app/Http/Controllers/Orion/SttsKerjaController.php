<?php

namespace App\Http\Controllers\Orion;

use App\Models\SttsKerja;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class SttsKerjaController extends Controller
{
    use DisableAuthorization;

    protected $model = SttsKerja::class;

    public function filterableBy(): array
    {
        return ['stts', 'ktg'];
    }

    public function searchableBy(): array
    {
        return ['stts', 'ktg'];
    }

    public function sortableBy(): array
    {
        return ['stts', 'ktg', 'indek'];
    }
}
