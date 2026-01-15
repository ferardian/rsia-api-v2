<?php

namespace App\Http\Controllers\Orion;

use App\Models\Bidang;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class BidangController extends Controller
{
    use DisableAuthorization;

    protected $model = Bidang::class;

    public function filterableBy(): array
    {
        return ['nama'];
    }

    public function searchableBy(): array
    {
        return ['nama'];
    }

    public function sortableBy(): array
    {
        return ['nama'];
    }
}
