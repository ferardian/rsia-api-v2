<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaMappingJabatan extends Model
{
    protected $table = 'rsia_mapping_jabatan';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'dep_id_up',
        'kd_jabatan_up',
        'dep_id_down',
        'kd_jbtn_down'
    ];
}
