<?php

namespace App\Http\Controllers\Orion;

use App\Models\JnjJabatan;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class JnjJabatanController extends Controller
{
    use DisableAuthorization;

    protected $model = JnjJabatan::class;

    public function filterableBy(): array
    {
        return ['nama', 'kode'];
    }

    public function searchableBy(): array
    {
        return ['nama', 'kode'];
    }

    public function sortableBy(): array
    {
        return ['nama', 'kode'];
    }
}
