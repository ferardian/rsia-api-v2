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
        'kd_jabatan_down'
    ];

    public function up_jabatan()
    {
        return $this->belongsTo(JnjJabatan::class, 'kd_jabatan_up', 'kode');
    }

    public function down_jabatan()
    {
        return $this->belongsTo(JnjJabatan::class, 'kd_jabatan_down', 'kode');
    }

    public function up_departemen()
    {
        return $this->belongsTo(Departemen::class, 'dep_id_up', 'dep_id');
    }

    public function down_departemen()
    {
        return $this->belongsTo(Departemen::class, 'dep_id_down', 'dep_id');
    }
}
