<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\ProcedureBilling
 *
 * @property string $no_rawat
 * @property string $kd_jenis_prw
 * @property string|null $kd_dokter
 * @property string|null $nip
 * @property string $nm_perawatan
 * @property string $status_rawat (Ralan/Ranap)
 * @property string $jenis_petugas (Dokter/Perawat/Dokter+Perawat)
 * @property float $material
 * @property float $bhp
 * @property float $tarif_tindakandr
 * @property float $tarif_tindakanpr
 * @property float $kso
 * @property float $menejemen
 * @property float $biaya_rawat
 * @property string|null $stts_bayar
 * @property \Illuminate\Support\Carbon|null $tgl_perawatan
 * @property \Illuminate\Support\Carbon|null $jam_rawat
 */
class ProcedureBilling extends Model
{
    use HasFactory;

    protected $table = 'procedure_billing';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    // Define relationships
    public function dokter()
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }

    public function petugas()
    {
        return $this->belongsTo(Petugas::class, 'nip', 'nip');
    }

    protected $fillable = [
        'no_rawat',
        'kd_jenis_prw',
        'kd_dokter',
        'nip',
        'nm_perawatan',
        'status_rawat',
        'jenis_petugas',
        'material',
        'bhp',
        'tarif_tindakandr',
        'tarif_tindakanpr',
        'kso',
        'menejemen',
        'biaya_rawat',
        'stts_bayar',
        'tgl_perawatan',
        'jam_rawat',
    ];

    protected $casts = [
        'material' => 'float',
        'bhp' => 'float',
        'tarif_tindakandr' => 'float',
        'tarif_tindakanpr' => 'float',
        'kso' => 'float',
        'menejemen' => 'float',
        'biaya_rawat' => 'float',
        'tgl_perawatan' => 'date',
        'jam_rawat' => 'string',
    ];

    /**
     * Get all procedure billing data by combining multiple tables
     */
    public static function getProcedureBillingData($noRawat = null)
    {
        $query = self::from(function ($query) {
            // Rawat Jalan - Dokter
            $query->select([
                'rjd.no_rawat',
                'rjd.kd_jenis_prw',
                'rjd.kd_dokter',
                DB::raw('NULL as nip'),
                'jp.nm_perawatan',
                DB::raw("'Ralan' as status_rawat"),
                DB::raw("'Dokter' as jenis_petugas"),
                'rjd.material',
                'rjd.bhp',
                'rjd.tarif_tindakandr',
                DB::raw('0 as tarif_tindakanpr'),
                'rjd.kso',
                'rjd.menejemen',
                'rjd.biaya_rawat',
                'rjd.stts_bayar',
                'rjd.tgl_perawatan',
                'rjd.jam_rawat',
                DB::raw('COALESCE(d.nm_dokter, "Dokter") as nama_petugas')
            ])
            ->from('rawat_jl_dr as rjd')
            ->leftJoin('jns_perawatan as jp', 'rjd.kd_jenis_prw', '=', 'jp.kd_jenis_prw')
            ->leftJoin('dokter as d', 'rjd.kd_dokter', '=', 'd.kd_dokter')

            // Rawat Jalan - Dokter + Perawat
            ->union(function ($query) {
                $query->select([
                    'rjdp.no_rawat',
                    'rjdp.kd_jenis_prw',
                    'rjdp.kd_dokter',
                    'rjdp.nip',
                    'jp.nm_perawatan',
                    DB::raw("'Ralan' as status_rawat"),
                    DB::raw("'Dokter+Perawat' as jenis_petugas"),
                    'rjdp.material',
                    'rjdp.bhp',
                    'rjdp.tarif_tindakandr',
                    'rjdp.tarif_tindakanpr',
                    'rjdp.kso',
                    'rjdp.menejemen',
                    'rjdp.biaya_rawat',
                    'rjdp.stts_bayar',
                    'rjdp.tgl_perawatan',
                    'rjdp.jam_rawat',
                    DB::raw('COALESCE(p.nama, "Petugas") as nama_petugas')
                ])
                ->from('rawat_jl_drpr as rjdp')
                ->leftJoin('jns_perawatan as jp', 'rjdp.kd_jenis_prw', '=', 'jp.kd_jenis_prw')
                ->leftJoin('petugas as p', 'rjdp.nip', '=', 'p.nip');
            })

            // Rawat Jalan - Perawat
            ->union(function ($query) {
                $query->select([
                    'rjp.no_rawat',
                    'rjp.kd_jenis_prw',
                    DB::raw('NULL as kd_dokter'),
                    'rjp.nip',
                    'jp.nm_perawatan',
                    DB::raw("'Ralan' as status_rawat"),
                    DB::raw("'Perawat' as jenis_petugas"),
                    'rjp.material',
                    'rjp.bhp',
                    DB::raw('0 as tarif_tindakandr'),
                    'rjp.tarif_tindakanpr',
                    'rjp.kso',
                    'rjp.menejemen',
                    'rjp.biaya_rawat',
                    'rjp.stts_bayar',
                    'rjp.tgl_perawatan',
                    'rjp.jam_rawat',
                    DB::raw('COALESCE(p.nama, "Petugas") as nama_petugas')
                ])
                ->from('rawat_jl_pr as rjp')
                ->leftJoin('jns_perawatan as jp', 'rjp.kd_jenis_prw', '=', 'jp.kd_jenis_prw')
                ->leftJoin('petugas as p', 'rjp.nip', '=', 'p.nip');
            })

            // Rawat Inap - Dokter
            ->union(function ($query) {
                $query->select([
                    'rid.no_rawat',
                    'rid.kd_jenis_prw',
                    'rid.kd_dokter',
                    DB::raw('NULL as nip'),
                    'jpi.nm_perawatan',
                    DB::raw("'Ranap' as status_rawat"),
                    DB::raw("'Dokter' as jenis_petugas"),
                    'rid.material',
                    'rid.bhp',
                    'rid.tarif_tindakandr',
                    DB::raw('0 as tarif_tindakanpr'),
                    'rid.kso',
                    'rid.menejemen',
                    'rid.biaya_rawat',
                    DB::raw("'Sudah' as stts_bayar"),
                    'rid.tgl_perawatan',
                    'rid.jam_rawat',
                    DB::raw('COALESCE(d.nm_dokter, "Dokter") as nama_petugas')
                ])
                ->from('rawat_inap_dr as rid')
                ->leftJoin('jns_perawatan_inap as jpi', 'rid.kd_jenis_prw', '=', 'jpi.kd_jenis_prw')
                ->leftJoin('dokter as d', 'rid.kd_dokter', '=', 'd.kd_dokter');
            })

            // Rawat Inap - Dokter + Perawat
            ->union(function ($query) {
                $query->select([
                    'ridpr.no_rawat',
                    'ridpr.kd_jenis_prw',
                    'ridpr.kd_dokter',
                    'ridpr.nip',
                    'jpi.nm_perawatan',
                    DB::raw("'Ranap' as status_rawat"),
                    DB::raw("'Dokter+Perawat' as jenis_petugas"),
                    'ridpr.material',
                    'ridpr.bhp',
                    'ridpr.tarif_tindakandr',
                    'ridpr.tarif_tindakanpr',
                    'ridpr.kso',
                    'ridpr.menejemen',
                    'ridpr.biaya_rawat',
                    DB::raw("'Sudah' as stts_bayar"),
                    'ridpr.tgl_perawatan',
                    'ridpr.jam_rawat',
                    DB::raw('COALESCE(p.nama, "Petugas") as nama_petugas')
                ])
                ->from('rawat_inap_drpr as ridpr')
                ->leftJoin('jns_perawatan_inap as jpi', 'ridpr.kd_jenis_prw', '=', 'jpi.kd_jenis_prw')
                ->leftJoin('petugas as p', 'ridpr.nip', '=', 'p.nip');
            })

            // Rawat Inap - Perawat
            ->union(function ($query) {
                $query->select([
                    'rip.no_rawat',
                    'rip.kd_jenis_prw',
                    DB::raw('NULL as kd_dokter'),
                    'rip.nip',
                    'jpi.nm_perawatan',
                    DB::raw("'Ranap' as status_rawat"),
                    DB::raw("'Perawat' as jenis_petugas"),
                    'rip.material',
                    'rip.bhp',
                    DB::raw('0 as tarif_tindakandr'),
                    'rip.tarif_tindakanpr',
                    'rip.kso',
                    'rip.menejemen',
                    'rip.biaya_rawat',
                    DB::raw("'Sudah' as stts_bayar"),
                    'rip.tgl_perawatan',
                    'rip.jam_rawat',
                    DB::raw('COALESCE(p.nama, "Petugas") as nama_petugas')
                ])
                ->from('rawat_inap_pr as rip')
                ->leftJoin('jns_perawatan_inap as jpi', 'rip.kd_jenis_prw', '=', 'jpi.kd_jenis_prw')
                ->leftJoin('petugas as p', 'rip.nip', '=', 'p.nip');
            });
        }, 'procedure_billing');

        if ($noRawat) {
            $query->where('no_rawat', $noRawat);
        }

        return $query;
    }

    /**
     * Get procedure billing by no_rawat
     */
    public static function getByNoRawat($noRawat)
    {
        return self::getProcedureBillingData($noRawat);
    }

    /**
     * Get total biaya calculated attribute
     */
    public function getTotalBiayaAttribute()
    {
        return $this->material + $this->bhp + $this->tarif_tindakandr + $this->tarif_tindakanpr +
               $this->kso + $this->menejemen;
    }

    /**
     * Get nama petugas attribute
     */
    public function getNamaPetugasAttribute()
    {
        if ($this->jenis_petugas === 'Dokter' && $this->kd_dokter) {
            // Get doctor name from database
            $dokter = DB::table('dokter')->where('kd_dokter', $this->kd_dokter)->first();
            return $dokter ? $dokter->nm_dokter : 'Dokter';
        } elseif ($this->nip) {
            // Get petugas name from database
            $petugas = DB::table('petugas')->where('nip', $this->nip)->first();
            return $petugas ? $petugas->nama : 'Petugas';
        }

        return $this->jenis_petugas;
    }
}