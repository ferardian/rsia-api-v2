<?php

namespace App\Http\Controllers\Orion;

use Orion\Http\Requests\Request;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RsiaPksController extends Controller
{
    use DisableAuthorization;

    /**
     * Fully-qualified model class name
     */
    protected $model = \App\Models\RsiaPks::class;
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
     * The attributes that are used for filtering.
     *
     * @return array
     */
    public function filterableBy(): array
    {
        return ['tgl_terbit', 'tanggal_awal', 'tanggal_akhir', 'pj', 'status', 'no_pks_internal'];
    }

    /**
     * The attributes that are used for sorting.
     *
     * @return array
     */
    public function sortableBy(): array
    {
        return ['id', 'judul', 'pj', 'tgl_terbit', 'tanggal_awal', 'tanggal_akhir'];
    }

    /**
     * The relations that are always included together with a resource.
     *
     * @return array
     */
    public function alwaysIncludes(): array
    {
        return ['penanggungJawab'];
    }

    /**
     * The relations that are allowed to be included together with a resource.
     *
     * @return array
     */
    public function includes(): array
    {
        return [];
    }

    /**
     * The attributes that are used for searching.
     *
     * @return array
     */
    public function searchableBy(): array
    {
        return ['judul', 'no_pks_internal', 'no_pks_eksternal', 'penanggungJawab.nama'];
    }

    protected function performStore(Request $request, Model $model, array $attributes): void
    {
        $file = $request->file('file');
        if ($file) {
            $fileName = strtotime(now()) . '-' . str_replace([' ', '_'], '-', $file->getClientOriginalName());
            $attributes['berkas'] = $fileName;

            $st = new Storage();
            if (!$st::disk('sftp')->exists(env('DOCUMENT_PKS_SAVE_LOCATION'))) {
                $st::disk('sftp')->makeDirectory(env('DOCUMENT_PKS_SAVE_LOCATION'));
            }
            $st::disk('sftp')->put(env('DOCUMENT_PKS_SAVE_LOCATION') . $fileName, file_get_contents($file));
        }

        $model->fill($attributes);
        $model->save();
    }

    protected function performUpdate(Request $request, Model $model, array $attributes): void
    {
        $file = $request->file('file');
        $oldBerkas = $model->berkas;

        if ($file) {
            $fileName = strtotime(now()) . '-' . str_replace([' ', '_'], '-', $file->getClientOriginalName());
            $attributes['berkas'] = $fileName;

            $st = new Storage();
            if (!$st::disk('sftp')->exists(env('DOCUMENT_PKS_SAVE_LOCATION'))) {
                $st::disk('sftp')->makeDirectory(env('DOCUMENT_PKS_SAVE_LOCATION'));
            }

            // Delete old file if exists
            if ($oldBerkas && $st::disk('sftp')->exists(env('DOCUMENT_PKS_SAVE_LOCATION') . $oldBerkas)) {
                $st::disk('sftp')->delete(env('DOCUMENT_PKS_SAVE_LOCATION') . $oldBerkas);
            }

            $st::disk('sftp')->put(env('DOCUMENT_PKS_SAVE_LOCATION') . $fileName, file_get_contents($file));
        }

        $model->fill($attributes);
        $model->save();
    }
}
