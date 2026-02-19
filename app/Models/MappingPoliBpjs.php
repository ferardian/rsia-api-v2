<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MappingPoliBpjs extends Model
{
    use HasFactory;

    protected $table = 'maping_poli_bpjs';
    protected $primaryKey = 'kd_poli_rs';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'kd_poli_rs',
        'kd_poli_bpjs',
        'nm_poli_bpjs',
    ];

    public function poliklinik()
    {
        return $this->belongsTo(Poliklinik::class, 'kd_poli_rs', 'kd_poli');
    }
}
