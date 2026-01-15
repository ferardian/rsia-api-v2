<?php

namespace App\Http\Controllers\Orion;

use App\Models\Jabatan;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class JabatanController extends Controller
{
    use DisableAuthorization;

    protected $model = Jabatan::class;

    public function filterableBy(): array
    {
        return ['kd_jbtn', 'nm_jbtn'];
    }

    public function searchableBy(): array
    {
        return ['kd_jbtn', 'nm_jbtn'];
    }

    public function sortableBy(): array
    {
        return ['kd_jbtn', 'nm_jbtn'];
    }
}
