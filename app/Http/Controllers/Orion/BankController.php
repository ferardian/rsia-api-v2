<?php

namespace App\Http\Controllers\Orion;

use App\Models\Bank;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class BankController extends Controller
{
    use DisableAuthorization;

    protected $model = Bank::class;

    public function filterableBy(): array
    {
        return ['namabank'];
    }

    public function searchableBy(): array
    {
        return ['namabank'];
    }

    public function sortableBy(): array
    {
        return ['namabank'];
    }
}
