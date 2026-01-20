<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaAnggotaKomite extends Model
{
    use HasFactory;

    protected $table = 'rsia_anggota_komite';

    protected $guarded = [];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nik', 'nik');
    }

    public function komite()
    {
        return $this->belongsTo(RsiaKomite::class, 'komite_id', 'id');
    }

    public function jabatan()
    {
        return $this->belongsTo(RsiaJabatanKomite::class, 'jabatan_id', 'id');
    }
}
