<?php

namespace App\Http\Controllers\Orion;

use Illuminate\Http\Request;
use Orion\Concerns\DisableAuthorization;

class JadwalDokterController extends \Orion\Http\Controllers\Controller
{
    use DisableAuthorization;

    /**
     * Fully-qualified model class name
     */
    protected $model = \App\Models\JadwalPoli::class;

    /**
     * @var string $resource
     */
    protected $resource = \App\Http\Resources\Jadwal\JadwalDokterResource::class;

    /**
     * @var string $collectionResource
     */
    protected $collectionResource = \App\Http\Resources\Jadwal\JadwalDokterCollection::class;

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
        return ['kd_dokter', 'hari_kerja', 'jam_mulai', 'jam_selesai', 'kd_poli', 'kuota'];
    }

    /**
     * The attributes that are used for searching.
     *
     * @return array
     */
    public function searchableBy(): array
    {
        return ['kd_dokter', 'hari_kerja', 'jam_mulai', 'jam_selesai', 'kd_poli'];
    }

    /**
     * The relations that are allowed to be included together with a resource.
     *
     * @return array
     */
    public function includes(): array
    {
        return ['dokter', 'dokter.spesialis', 'dokter.pegawai', 'poliklinik'];
    }

    /**
     * The attributes that are used for filtering.
     *
     * @return array
     */
    public function filterableBy(): array
    {
        return ['kd_dokter', 'hari_kerja', 'jam_mulai', 'jam_selesai', 'kd_poli', 'kuota'];
    }

    /**
     * Custom update method to handle composite key
     */
    public function customUpdate(\Illuminate\Http\Request $request, $resourceKey)
    {
        // Decode URL-encoded key
        $resourceKey = urldecode($resourceKey);
        
        // Parse composite key: kd_dokter,hari_kerja,jam_mulai
        $keys = explode(',', $resourceKey);
        if (count($keys) !== 3) {
            return response()->json(['message' => 'Invalid composite key format'], 400);
        }

        [$kd_dokter, $hari_kerja, $jam_mulai] = $keys;

        // Find the record
        $jadwal = $this->model::where('kd_dokter', $kd_dokter)
            ->where('hari_kerja', $hari_kerja)
            ->where('jam_mulai', $jam_mulai)
            ->first();

        if (!$jadwal) {
            return response()->json(['message' => 'Schedule not found'], 404);
        }

        // Update the record
        $jadwal->update($request->all());

        return response()->json([
            'data' => new $this->resource($jadwal)
        ]);
    }

    /**
     * Custom destroy method to handle composite key
     */
    public function customDestroy($resourceKey)
    {
        // Decode URL-encoded key
        $resourceKey = urldecode($resourceKey);
        
        // Parse composite key: kd_dokter,hari_kerja,jam_mulai
        $keys = explode(',', $resourceKey);
        if (count($keys) !== 3) {
            return response()->json(['message' => 'Invalid composite key format'], 400);
        }

        [$kd_dokter, $hari_kerja, $jam_mulai] = $keys;

        // Find and delete the record
        $deleted = $this->model::where('kd_dokter', $kd_dokter)
            ->where('hari_kerja', $hari_kerja)
            ->where('jam_mulai', $jam_mulai)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Schedule not found'], 404);
        }

        return response()->json(['message' => 'Schedule deleted successfully']);
    }
}
