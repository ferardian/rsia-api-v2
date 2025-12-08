<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;
use Thiagoprz\CompositeKey\HasCompositeKey;

/**
 * App\Models\ResepPulang
 *
 * @property string $no_rawat
 * @property string $kode_brng
 * @property float $jml_barang
 * @property float $harga
 * @property float $total
 * @property string $dosis
 * @property string $tanggal
 * @property string $jam
 * @property string $kd_bangsal
 * @property string $no_batch
 * @property string $no_faktur
 * @property-read \App\Models\DataBarang $dataBarang
 * @property-read \App\Models\Bangsal $bangsal
 * @property-read \App\Models\RegPeriksa $regPeriksa
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang query()
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang whereNoRawat($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang whereKodeBrng($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang whereJmlBarang($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang whereHarga($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang whereDosis($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang whereTanggal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang whereJam($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang whereKdBangsal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang whereNoBatch($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ResepPulang whereNoFaktur($value)
 * @mixin \Eloquent
 */
class ResepPulang extends Model
{
    use HasFactory, HasCompositeKey, Compoships;

    protected $table = 'resep_pulang';

    protected $primaryKey = ["no_rawat", "kode_brng", "tanggal", "jam", "no_batch", "no_faktur"];

    protected $guarded = [];

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'no_rawat' => 'string',
        'kode_brng' => 'string',
        'jml_barang' => 'float',
        'harga' => 'float',
        'total' => 'float',
        'tanggal' => 'date',
        'jam' => 'datetime:H:i:s',
        'kd_bangsal' => 'string',
        'no_batch' => 'string',
        'no_faktur' => 'string',
    ];

    /**
     * Get the databarang associated with the resep pulang.
     */
    public function dataBarang()
    {
        return $this->belongsTo(DataBarang::class, 'kode_brng', 'kode_brng');
    }

    /**
     * Get the bangsal associated with the resep pulang.
     */
    public function bangsal()
    {
        return $this->belongsTo(Bangsal::class, 'kd_bangsal', 'kd_bangsal');
    }

    /**
     * Get the reg_periksa associated with the resep pulang.
     */
    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    /**
     * Alias for dataBarang for backward compatibility
     */
    public function obat()
    {
        return $this->dataBarang();
    }
}
