<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalTambahan extends Model
{
    use HasFactory;

    protected $table = 'jadwal_tambahan';
    protected $guarded = [];
    public $timestamps = false; // Table doesn't have created_at/updated_at

    public function pegawai()
    {
        return $this->hasOne(Pegawai::class, 'id', 'id');
    }

    public function jam_masuk()
    {
        return $this->hasOne(JamMasuk::class, 'shift', 'H' . date('d'));
    }
}
