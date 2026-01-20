<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaKomite extends Model
{
    use HasFactory;

    protected $table = 'rsia_komite';

    protected $guarded = [];

    public function anggota()
    {
        return $this->hasMany(RsiaAnggotaKomite::class, 'komite_id', 'id');
    }
}
