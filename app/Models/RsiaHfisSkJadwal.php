<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaHfisSkJadwal extends Model
{
    use HasFactory;

    protected $table = 'rsia_hfis_sk_jadwal';
    protected $guarded = [];

    public function detail()
    {
        return $this->hasMany(RsiaHfisSkJadwalDetail::class, 'sk_id', 'id');
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }
}
