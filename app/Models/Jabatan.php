<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jabatan extends Model
{
    use HasFactory;

    protected $table = 'jabatan';
    protected $primaryKey = 'kd_jbtn';
    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    public function petugas()
    {
        return $this->hasMany(Petugas::class, 'kd_jbtn', 'kd_jbtn');
    }
}
