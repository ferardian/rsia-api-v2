<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaMasterInmut extends Model
{
    protected $table = 'rsia_master_inmut';
    protected $primaryKey = 'id_inmut';
    public $timestamps = false;

    protected $fillable = [
        'nama_inmut',
        'id_jenis',
        'dep_id',
        'nama_ruang',
        'standar',
        'rumus',
        'status',
        'nama_jenis',
        'ket_num',
        'ket_denum',
        'definisi_operasional',
        'satuan',
        'formula',
        'id_master'
    ];

    // Optional: If id_jenis or dep_id relates to other tables, defined relationships here later.
    public function masterUtama()
    {
        return $this->belongsTo(RsiaMasterInmutUtama::class, 'id_master', 'id_master');
    }
}
