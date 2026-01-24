<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PermintaanLab extends Model
{
    use HasFactory;

    protected $table = 'permintaan_lab';

    protected $primaryKey = 'noorder';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * Get the registrasi that owns the permintaan lab.
     */
    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    /**
     * Get the dokter that perujuk the permintaan lab.
     */
    public function perujuk()
    {
        return $this->belongsTo(Dokter::class, 'dokter_perujuk', 'kd_dokter');
    }

    /**
     * Get the periksa lab for the permintaan lab.
     * Note: Usually linked via no_rawat and tgl_periksa/jam (approximate) or a mapping table.
     * RSIA usually links them by no_rawat.
     */
    public function periksaLab()
    {
        return $this->hasMany(PeriksaLab::class, 'no_rawat', 'no_rawat');
    }
}
