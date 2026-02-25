<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * App\Models\RsiaKualifikasiStafKlinis
 *
 * @property string $nik
 * @property string $kategori_profesi
 * @property string $tanggal_str
 * @property string $tanggal_akhir_str
 * @property string $nomor_str
 * @property string $nomor_sip
 * @property string $tanggal_izin_praktek
 * @property string $perguruan_tinggi
 * @property string $prodi
 * @property string $tanggal_lulus
 * @property int $status
 * @property string $tgl_update
 * @property-read \App\Models\Pegawai $pegawai
 */
class RsiaKualifikasiStafKlinis extends Model
{
    use HasFactory;

    protected $table = 'rsia_kualifikasi_staf_klinis';

    protected $primaryKey = 'nik';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    const UPDATED_AT = 'tgl_update';

    protected $fillable = [
        'nik',
        'kategori_profesi',
        'tanggal_str',
        'tanggal_akhir_str',
        'nomor_str',
        'nomor_sip',
        'tanggal_izin_praktek',
        'perguruan_tinggi',
        'prodi',
        'tanggal_lulus',
        'bukti_kelulusan',
        'status',
    ];

    protected $casts = [
        'nik' => 'string',
        'status' => 'integer',
        'tanggal_str' => 'date',
        'tanggal_akhir_str' => 'date',
        'tanggal_izin_praktek' => 'date',
        'tanggal_lulus' => 'date',
    ];

    /**
     * Boot method to auto-update tgl_update
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->tgl_update = now();
        });
    }

    /**
     * Relationship to Pegawai
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nik', 'nik');
    }

    /**
     * Scope for active records
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope for expired STR
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpiredStr($query)
    {
        return $query->where('tanggal_akhir_str', '<', now());
    }

    /**
     * Scope for STR expiring soon (within 90 days)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpiringSoon($query)
    {
        return $query->whereBetween('tanggal_akhir_str', [now(), now()->addDays(90)]);
    }

    /**
     * Check if STR is expired
     *
     * @return bool
     */
    public function getIsStrExpiredAttribute()
    {
        return $this->tanggal_akhir_str < now();
    }

    /**
     * Check if STR is expiring soon (within 90 days)
     *
     * @return bool
     */
    public function getIsStrExpiringSoonAttribute()
    {
        $daysUntilExpiry = now()->diffInDays($this->tanggal_akhir_str, false);
        return $daysUntilExpiry > 0 && $daysUntilExpiry <= 90;
    }
}
