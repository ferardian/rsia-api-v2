<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaHfisSkJadwalDetail extends Model
{
    use HasFactory;

    protected $table = 'rsia_hfis_sk_jadwal_detail';
    protected $guarded = [];
    public $timestamps = false;

    public function header()
    {
        return $this->belongsTo(RsiaHfisSkJadwal::class, 'sk_id', 'id');
    }
}
