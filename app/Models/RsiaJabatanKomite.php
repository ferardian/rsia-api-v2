<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaJabatanKomite extends Model
{
    use HasFactory;

    protected $table = 'rsia_jabatan_komite';

    protected $guarded = [];

    public function anggota()
    {
        return $this->hasMany(RsiaAnggotaKomite::class, 'jabatan_id', 'id');
    }
}
