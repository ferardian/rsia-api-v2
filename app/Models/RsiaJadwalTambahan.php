<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaJadwalTambahan extends Model
{
    use HasFactory;

    protected $table = 'rsia_jadwal_tambahan';
    public $timestamps = false;
    protected $guarded = [];

    public function pegawai()
    {
        return $this->hasOne(Pegawai::class, 'id', 'id');
    }

    public function jam_masuk()
    {
        return $this->hasOne(JamMasuk::class, 'shift', 'H' . date('d'));
    }
}
