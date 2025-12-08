<?php

namespace App\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * App\Models\GambarRadiologi
 *
 * @property string $no_rawat
 * @property string $tgl_periksa
 * @property string $jam
 * @property string $lokasi_gambar
 */
class GambarRadiologi extends Model
{
    use HasFactory, Compoships;

    protected $table = 'gambar_radiologi';

    protected $primaryKey = ['no_rawat', 'tgl_periksa', 'jam', 'lokasi_gambar'];

    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    /**
     * Get the periksa radiologi that owns the gambar.
     */
    public function periksaRadiologi()
    {
        return $this->belongsTo(PeriksaRadiologi::class, ['no_rawat', 'tgl_periksa', 'jam'], ['no_rawat', 'tgl_periksa', 'jam']);
    }

    
    /**
     * Scope query untuk no_rawat.
     */
    public function scopeWhereNoRawat($query, $noRawat)
    {
        return $query->where('no_rawat', $noRawat);
    }

    /**
     * Scope query untuk tgl_periksa.
     */
    public function scopeWhereTglPeriksa($query, $tglPeriksa)
    {
        return $query->where('tgl_periksa', $tglPeriksa);
    }

    /**
     * Scope query untuk jam.
     */
    public function scopeWhereJam($query, $jam)
    {
        return $query->where('jam', $jam);
    }

    /**
     * Scope query untuk lokasi_gambar.
     */
    public function scopeWhereLokasiGambar($query, $lokasiGambar)
    {
        return $query->where('lokasi_gambar', $lokasiGambar);
    }
}