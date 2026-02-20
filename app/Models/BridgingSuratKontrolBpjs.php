<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BridgingSuratKontrolBpjs extends Model
{
    use HasFactory;

    protected $table = 'bridging_surat_kontrol_bpjs';

    protected $primaryKey = 'no_surat';

    protected $guarded = [];

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'tgl_surat'   => 'date',
        'tgl_rencana' => 'date',
    ];

    /**
     * Relasi ke SEP asal (dari no_sep)
     */
    public function sep()
    {
        return $this->belongsTo(BridgingSep::class, 'no_sep', 'no_sep');
    }

    /**
     * Relasi ke SEP kontrol (no_surat == noskdp di bridging_sep)
     * Digunakan untuk cek apakah sudah ada SEP baru untuk kunjungan kontrol
     */
    public function sep2()
    {
        return $this->hasOne(BridgingSep::class, 'noskdp', 'no_surat');
    }
}

