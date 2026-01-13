<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaRekapInmut extends Model
{
    protected $table = 'rsia_rekap_inmut';
    protected $primaryKey = 'id_rekap';
    public $timestamps = false;

    protected $fillable = [
        'id_inmut', 'nama_inmut', 'dep_id', 'nama_ruang',
        'tanggal_inmut', 'num', 'denum', 'tanggal_input'
    ];

    protected $casts = [
        'tanggal_inmut' => 'date',
        'tanggal_input' => 'datetime',
        'num' => 'integer',
        'denum' => 'integer',
    ];

    public function indikator()
    {
        return $this->belongsTo(RsiaMasterInmut::class, 'id_inmut', 'id_inmut');
    }
}
