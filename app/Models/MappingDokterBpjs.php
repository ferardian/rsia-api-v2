<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MappingDokterBpjs extends Model
{
    use HasFactory;

    protected $table = 'maping_dokter_dpjpvclaim';
    protected $primaryKey = 'kd_dokter';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'kd_dokter',
        'kd_dokter_bpjs',
        'nm_dokter_bpjs',
    ];

    /**
     * Get the doctor associated with the mapping.
     */
    public function dokter()
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }
}
