<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaAnalisaInmut extends Model
{
    protected $table = 'rsia_analisa_inmut';
    protected $primaryKey = 'id_analisa';
    public $timestamps = false;

    protected $fillable = [
        'analisa', 'tindak_lanjut', 'dep_id', 'id_inmut',
        'jml_num', 'jml_denum', 'tanggal_awal', 'tanggal_akhir',
        'nama_ruang', 'nama_inmut', 'jumlah'
    ];

    protected $casts = [
        'tanggal_awal' => 'date',
        'tanggal_akhir' => 'date',
        'jml_num' => 'integer',
        'jml_denum' => 'integer',
        'jumlah' => 'double',
    ];

    public function indikator()
    {
        return $this->belongsTo(RsiaMasterInmut::class, 'id_inmut', 'id_inmut');
    }
}
