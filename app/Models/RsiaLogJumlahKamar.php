<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaLogJumlahKamar extends Model
{
    use HasFactory;

    protected $table = 'rsia_log_jumlah_kamar';

    protected $primaryKey = ['tahun', 'bulan', 'kategori'];
    
    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'tahun' => 'string',
        'bulan' => 'string',
        'kategori' => 'string',
        'jumlah' => 'integer',
    ];
}
