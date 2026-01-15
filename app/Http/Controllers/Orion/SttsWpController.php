<?php

namespace App\Http\Controllers\Orion;

use App\Models\SttsWp;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class SttsWpController extends Controller
{
    use DisableAuthorization;

    protected $model = SttsWp::class;

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
        return ['stts', 'ktg'];
    }
}
