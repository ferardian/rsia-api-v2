<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\RsiaMappingProcedure
 *
 * @property string $kd_jenis_prw
 * @property string|null $code
 * @property string $system
 * @property string|null $display
 * @property string|null $description
 * @property string $status
 * @property string|null $notes
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class RsiaMappingProcedure extends Model
{
    use HasFactory;

    protected $table = 'rsia_mapping_procedure';

    protected $primaryKey = 'kd_jenis_prw';

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = [
        'kd_jenis_prw',
        'code',
        'system',
        'display',
        'description',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'kd_jenis_prw' => 'string',
        'code' => 'string',
        'system' => 'string',
        'display' => 'string',
        'description' => 'string',
        'status' => 'string',
        'notes' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the procedure information from jns_perawatan
     */
    public function jenisPerawatan()
    {
        return $this->belongsTo(\App\Models\JenisPerawatan::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }

    /**
     * Get the procedure information from jns_perawatan_inap (for inpatient procedures)
     */
    public function jenisPerawatanInap()
    {
        return $this->belongsTo(\App\Models\JenisPerawatanInap::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }

    /**
     * Scope to get only active mappings
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to search by SNOMED code or display name
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'like', "%{$term}%")
              ->orWhere('display', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * Scope to search by procedure name
     */
    public function scopeByProcedureName($query, $procedureName)
    {
        return $query->whereHas('jenisPerawatan', function ($q) use ($procedureName) {
            $q->where('nm_perawatan', 'like', "%{$procedureName}%");
        });
    }

    /**
     * Get formatted SNOMED code display
     */
    public function getFormattedSnomedAttribute()
    {
        if (!$this->code || !$this->display) {
            return null;
        }

        return [
            'code' => $this->code,
            'system' => $this->system,
            'display' => $this->display,
            'formatted' => "{$this->code} - {$this->display}"
        ];
    }

    /**
     * Check if mapping exists for given procedure code
     */
    public static function hasMapping($kdJenisPrw)
    {
        return static::where('kd_jenis_prw', $kdJenisPrw)
                    ->whereNotNull('code')
                    ->whereNotNull('display')
                    ->where('status', 'active')
                    ->exists();
    }

    /**
     * Get mapping by procedure code
     */
    public static function getByProcedureCode($kdJenisPrw)
    {
        return static::where('kd_jenis_prw', $kdJenisPrw)
                    ->active()
                    ->with('jenisPerawatan')
                    ->first();
    }

    /**
     * Bulk update procedure mappings
     */
    public static function bulkUpdate(array $mappings, $updatedBy = null)
    {
        foreach ($mappings as $kdJenisPrw => $mapping) {
            static::updateOrCreate(
                ['kd_jenis_prw' => $kdJenisPrw],
                [
                    'code' => $mapping['code'] ?? null,
                    'system' => $mapping['system'] ?? 'http://snomed.info/sct',
                    'display' => $mapping['display'] ?? null,
                    'description' => $mapping['description'] ?? null,
                    'status' => $mapping['status'] ?? 'active',
                    'notes' => $mapping['notes'] ?? null,
                    'updated_by' => $updatedBy,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
