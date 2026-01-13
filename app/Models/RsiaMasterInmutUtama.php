<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaMasterInmutUtama extends Model
{
    protected $table = 'rsia_master_inmut_utama';
    protected $primaryKey = 'id_master';
    public $timestamps = false;

    protected $fillable = [
        'nama_inmut',
        'dasar_pemikiran',
        'dimensi',
        'tujuan',
        'definisi',
        'kategori',
        'jenis_indikator',
        'satuan',
        'ket_num',
        'ket_denum',
        'standar',
        'rumus',
        'kriteria',
        'formula',
        'metode_pengumpulan_data',
        'sumber_data',
        'instrumen_pengambilan_data',
        'besar_sampel',
        'cara_pengambilan_sampel',
        'periode_pengumpulan_data',
        'penyajian_data',
        'periode_analisis',
        'pj'
    ];
}
