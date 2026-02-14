<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaRiwayatImunisasi extends Model
{
    protected $table = 'rsia_riwayat_imunisasi';
    protected $guarded = [];

    public $timestamps = true;

    public function master()
    {
        return $this->belongsTo(RsiaMasterImunisasi::class, 'master_imunisasi_id');
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'no_rkm_medis', 'no_rkm_medis');
    }
}
