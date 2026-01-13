<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PemeliharaanGedung extends Model
{
    protected $table = 'pemeliharaan_gedung';
    protected $primaryKey = 'no_pemeliharaan';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'no_pemeliharaan',
        'tanggal',
        'uraian_kegiatan',
        'nip',
        'pelaksana',
        'biaya',
        'jenis_pemeliharaan',
        'tindak_lanjut'
    ];

    protected $casts = [
        'tanggal' => 'date',
        'biaya' => 'float'
    ];

    public function petugas()
    {
        return $this->belongsTo(Petugas::class, 'nip', 'nip');
    }
}
