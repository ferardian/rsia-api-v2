<?php

namespace App\Http\Controllers\Orion;

use App\Models\Pendidikan;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class PendidikanController extends Controller
{
    use DisableAuthorization;

    protected $model = Pendidikan::class;

    public function filterableBy(): array
    {
        return ['tingkat'];
    }

    public function searchableBy(): array
    {
        return ['tingkat'];
    }

    public function sortableBy(): array
    {
        return ['tingkat', 'indek'];
    }
}
