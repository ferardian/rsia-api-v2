<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaMappingPerformerRole extends Model
{
    use HasFactory;

    protected $table = 'rsia_mapping_performer_role';

    protected $primaryKey = 'kd_jbtn';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kd_jbtn',
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
     * Get the jabatan that is related to this performer role mapping.
     */
    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class, 'kd_jbtn', 'kd_jbtn');
    }


    /**
     * Scope a query to only include active mappings.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to search by code or display.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $searchTerm
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('code', 'like', "%{$searchTerm}%")
              ->orWhere('display', 'like', "%{$searchTerm}%")
              ->orWhere('description', 'like', "%{$searchTerm}%");
        });
    }

    /**
     * Bulk update performer role mappings.
     *
     * @param  array  $mappings
     * @param  string  $updatedBy
     * @return int
     */
    public static function bulkUpdate($mappings, $updatedBy)
    {
        $count = 0;

        foreach ($mappings as $idPetugas => $mappingData) {
            $mappingData['updated_by'] = $updatedBy;

            self::updateOrCreate(
                ['id_petugas' => $idPetugas],
                $mappingData
            );

            $count++;
        }

        return $count;
    }
}