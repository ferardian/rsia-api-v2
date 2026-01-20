<?php

namespace App\Http\Controllers\Orion;

use App\Models\Departemen;
use Illuminate\Http\Request;

class DepartemenController extends \Orion\Http\Controllers\Controller
{
    use \Orion\Concerns\DisableAuthorization;

    protected $model = Departemen::class;

    /**
     * Retrieves currently authenticated user based on the guard.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function resolveUser()
    {
        return \Illuminate\Support\Facades\Auth::user();
    }

    public function generateNextId()
    {
        $lastId = Departemen::where('dep_id', 'like', 'DM%')
            ->orderByRaw('CAST(SUBSTRING(dep_id, 3) AS UNSIGNED) DESC')
            ->first()?->dep_id;

        if (!$lastId) {
            return response()->json([
                'status' => 'success',
                'data' => 'DM01'
            ]);
        }

        $number = (int) substr($lastId, 2);
        $nextId = 'DM' . str_pad($number + 1, 2, '0', STR_PAD_LEFT);

        return response()->json([
            'status' => 'success',
            'data' => $nextId
        ]);
    }

    /**
     * The attributes that are used for filtering.
     *
     * @return array
     */
    public function filterableBy(): array
    {
        return ['nama', 'dep_id', 'aktif'];
    }

    /**
     * The attributes that are used for sorting.
     *
     * @return array
     */
    public function sortableBy(): array
    {
        return ['dep_id', 'nama', 'kelompok'];
    }

    /**
     * The attributes that are used for searching.
     *
     * @return array
     */
    public function searchableBy(): array
    {
        return ['dep_id', 'nama', 'kelompok'];
    }
}
