<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel rsia_erm_bpjs
 *
 * Tabel ini menyimpan data Electronic Medical Record (ERM) yang dikirim ke BPJS,
 * termasuk bundle FHIR asli sebelum enkripsi dan response dari BPJS.
 *
 * @package App\Models
 */
class RsiaErmBpjs extends Model
{
    protected $table = 'rsia_erm_bpjs';

    protected $primaryKey = 'nosep';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nosep',
        'erm_request',
        'erm_response',
    ];

    protected $casts = [
        'erm_request' => 'array',
        'erm_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi ke tabel bridging_sep
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sep()
    {
        return $this->belongsTo(BridgingSep::class, 'nosep', 'no_sep');
    }

    /**
     * Simpan bundle ERM sebelum enkripsi ke database
     *
     * Fungsi ini menyimpan bundle FHIR asli yang belum dikompres dan dienkripsi.
     * Data ini berguna untuk debugging dan audit trail.
     *
     * @param string $noSep Nomor SEP
     * @param array $ermBundle Bundle FHIR yang akan dikirim ke BPJS
     * @param array $metadata Metadata tambahan (jenis pelayanan, user agent, dll)
     * @return \App\Models\RsiaErmBpjs
     */
    public static function saveErmRequest($noSep, $ermBundle, $metadata = [])
    {
        $requestData = [
            'bundle' => $ermBundle,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ];

        return self::updateOrCreate(
            ['nosep' => $noSep],
            ['erm_request' => $requestData]
        );
    }

    /**
     * Simpan response dari BPJS ke database
     *
     * Fungsi ini menyimpan response yang diterima dari BPJS API,
     * termasuk metadata seperti HTTP status, headers, dan processing time.
     *
     * @param string $noSep Nomor SEP
     * @param array $response Response dari BPJS API
     * @param array $metadata Metadata tambahan (HTTP status, headers, processing time)
     * @return \App\Models\RsiaErmBpjs
     */
    public static function saveErmResponse($noSep, $response, $metadata = [])
    {
        $responseData = [
            'response' => $response,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ];

        return self::updateOrCreate(
            ['nosep' => $noSep],
            ['erm_response' => $responseData]
        );
    }

    /**
     * Simpan request dan response BPJS sekaligus
     *
     * Fungsi ini menyimpan bundle ERM dan response BPJS dalam satu operasi database.
     * Cocok digunakan untuk update record yang sudah ada.
     *
     * @param string $noSep Nomor SEP
     * @param array $ermBundle Bundle FHIR yang dikirim
     * @param array $bpjsResponse Response dari BPJS
     * @param array $requestMetadata Metadata untuk request
     * @param array $responseMetadata Metadata untuk response
     * @return \App\Models\RsiaErmBpjs
     */
    public static function saveErmData($noSep, $ermBundle, $bpjsResponse, $requestMetadata = [], $responseMetadata = [])
    {
        $requestData = [
            'bundle' => $ermBundle,
            'metadata' => $requestMetadata,
            'timestamp' => now()->toISOString(),
        ];

        $responseData = [
            'response' => $bpjsResponse,
            'metadata' => $responseMetadata,
            'timestamp' => now()->toISOString(),
        ];

        return self::updateOrCreate(
            ['nosep' => $noSep],
            [
                'erm_request' => $requestData,
                'erm_response' => $responseData,
            ]
        );
    }

    /**
     * Mendapatkan bundle FHIR asli dari ERM request
     *
     * @return array|null
     */
    public function getBundleAttribute()
    {
        return $this->erm_request['bundle'] ?? null;
    }

    /**
     * Mendapatkan response dari BPJS
     *
     * @return array|null
     */
    public function getResponseAttribute()
    {
        return $this->erm_response['response'] ?? null;
    }

    /**
     * Mendapatkan metadata request
     *
     * @return array
     */
    public function getRequestMetadataAttribute()
    {
        return $this->erm_request['metadata'] ?? [];
    }

    /**
     * Mendapatkan metadata response
     *
     * @return array
     */
    public function getResponseMetadataAttribute()
    {
        return $this->erm_response['metadata'] ?? [];
    }
}