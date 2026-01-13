<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaDiklat extends Model
{
    protected $table = 'rsia_diklat';
    public $timestamps = false;
    protected $guarded = [];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_peg', 'id');
    }

    public function kegiatan()
    {
        return $this->belongsTo(RsiaKegiatan::class, 'id_kegiatan', 'id');
    }
}
