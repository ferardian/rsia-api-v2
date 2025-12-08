<?php

namespace App\Http\Controllers\Orion;

use Illuminate\Http\Request;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class PegawaiController extends Controller
{
    use DisableAuthorization;

    /**
     * Fully-qualified model class name
     */
    protected $model = \App\Models\Pegawai::class;

    /**
     * Override index method to include role data via LEFT JOIN
     */
    public function index(Request $request)
    {
        // Build query with LEFT JOIN for roles
        $query = $this->buildQuery($request);

        // Add LEFT JOIN for role data
        $query->leftJoin('rsia_user_role as ur', function($join) {
            $join->on('ur.nip', '=', 'pegawai.nik')
                 ->where('ur.is_active', 1);
        })
        ->leftJoin('rsia_role as r', 'ur.id_role', '=', 'r.id_role')
        ->addSelect([
            'r.id_role as role_id',
            'r.nama_role as role_name'
        ]);

        // Apply filters, sorting, etc.
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);
        $this->applyIncludes($query, $request);

        // Get results
        $models = $this->runIndexQuery($query, $request);

        // Transform using custom collection resource
        $resourceClass = $this->collectionResource ?? \Orion\Http\Resources\ResourcesCollection::class;
        $resource = new $resourceClass($models);

        return $resource->additional($this->indexMeta($request));
    }

    /**
     * @var string $resource
     */
    protected $resource = \App\Http\Resources\Pegawai\PegawaiResource::class;

    /**
     * @var string $collectionResource
     */
    protected $collectionResource = \App\Http\Resources\Pegawai\PegawaiCollection::class;

    /**
     * Retrieves currently authenticated user based on the guard.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function resolveUser()
    {
        return \Illuminate\Support\Facades\Auth::guard('user-aes')->user();
    }

    /**
     * The attributes that are used for sorting.
     *
     * @return array
     */
    public function sortableBy(): array
    {
        return ['nama', 'nik'];
    }

    /**
     * The attributes that are used for searching.
     *
     * @return array
     */
    public function searchableBy(): array
    {
        return ['nik', 'nama'];
    }

    /**
     * The relations that are allowed to be included together with a resource.
     *
     * @return array
     */
    public function includes(): array
    {
        return ['dep', 'berkas', 'presensi', 'petugas', 'email', 'statusKerja'];
    }

    /**
     * The attributes that are used for filtering.
     *
     * @return array
     */
    public function filterableBy(): array
    {
        return ['stts_aktif', 'departemen', 'nik', 'departemen', 'jbtn'];
    }
}
