<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaNomorKartuPegawai extends Model
{
    protected $table = 'rsia_nomor_kartu_pegawai';

    protected $primaryKey = 'nip';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'nip',
        'no_bpjs',
        'no_bpjstk'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nip', 'nik');
    }
}
