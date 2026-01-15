<?php

namespace App\Http\Controllers\Orion;

use App\Models\KelompokJabatan;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class KelompokJabatanController extends Controller
{
    use DisableAuthorization;

    protected $model = KelompokJabatan::class;

    public function filterableBy(): array
    {
        return ['kode_kelompok', 'nama_kelompok'];
    }

    public function searchableBy(): array
    {
        return ['kode_kelompok', 'nama_kelompok'];
    }

    public function sortableBy(): array
    {
        return ['kode_kelompok', 'nama_kelompok'];
    }
}
