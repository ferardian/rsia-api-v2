<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\RsiaMappingProcedureInap
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
class RsiaMappingProcedureInap extends Model
{
    use HasFactory;

    protected $table = 'rsia_mapping_procedure_inap';

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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the jenis perawatan inap that owns the mapping.
     */
    public function jenisPerawatanInap()
    {
        return $this->belongsTo(JenisPerawatanInap::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }

    /**
     * Scope to get only active mappings.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get only inactive mappings.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope to get only draft mappings.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to search in display and description fields.
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('display', 'like', "%{$searchTerm}%")
              ->orWhere('description', 'like', "%{$searchTerm}%")
              ->orWhere('code', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Get mappings by SNOMED code.
     */
    public static function getBySnomedCode($code)
    {
        return self::with('jenisPerawatanInap')
                   ->where('code', $code)
                   ->active()
                   ->get();
    }

    /**
     * Check if a procedure has SNOMED mapping.
     */
    public static function hasMapping($kdJenisPrw)
    {
        return self::where('kd_jenis_prw', $kdJenisPrw)
                   ->whereNotNull('code')
                   ->whereNotNull('display')
                   ->where('status', 'active')
                   ->exists();
    }

    /**
     * Create or update mapping.
     */
    public static function createOrUpdate($kdJenisPrw, $data, $updatedBy = 'system')
    {
        $mapping = self::find($kdJenisPrw);

        if ($mapping) {
            // Update existing
            $mapping->update(array_merge($data, [
                'updated_by' => $updatedBy
            ]));
        } else {
            // Create new
            $mapping = self::create(array_merge($data, [
                'kd_jenis_prw' => $kdJenisPrw,
                'created_by' => $updatedBy,
                'updated_by' => $updatedBy
            ]));
        }

        return $mapping;
    }
}