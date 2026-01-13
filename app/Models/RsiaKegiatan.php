<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaKegiatan extends Model
{
    protected $table = 'rsia_kegiatan';
    public $timestamps = false;
    protected $guarded = [];

    public function diklat()
    {
        return $this->hasMany(RsiaDiklat::class, 'id_kegiatan', 'id');
    }
}
