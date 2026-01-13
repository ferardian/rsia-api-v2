<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpsrsJenisBarang extends Model
{
    protected $table = 'ipsrsjenisbarang';
    protected $primaryKey = 'kd_jenis';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'kd_jenis',
        'nm_jenis'
    ];
}
