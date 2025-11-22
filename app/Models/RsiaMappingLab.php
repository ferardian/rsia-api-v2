<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\RsiaMappingLab
 *
 * @property string $kd_jenis_prw
 * @property string|null $code
 * @property string $system
 * @property string|null $display
 * @property-read \App\Models\JenisPerawatanLab $jenisPerawatanLab
 * @method static \Illuminate\Database\Eloquent\Builder|RsiaMappingLab newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RsiaMappingLab newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RsiaMappingLab query()
 * @method static \Illuminate\Database\Eloquent\Builder|RsiaMappingLab whereKdJenisPrw($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RsiaMappingLab whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RsiaMappingLab whereSystem($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RsiaMappingLab whereDisplay($value)
 * @mixin \Eloquent
 */
class RsiaMappingLab extends Model
{
    use HasFactory;

    protected $table = 'rsia_mapping_lab';

    protected $primaryKey = 'kd_jenis_prw';

    protected $keyType = 'string';

    protected $fillable = [
        'kd_jenis_prw',
        'code',
        'system',
        'display'
    ];

    public $timestamps = false;

    /**
     * Get the jenis perawatan lab that owns the mapping lab.
     */
    public function jenisPerawatanLab()
    {
        return $this->belongsTo(JenisPerawatanLab::class, 'kd_jenis_prw', 'kd_jenis_prw');
    }
}