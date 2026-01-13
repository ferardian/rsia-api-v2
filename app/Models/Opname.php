<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

class Opname extends Model
{
    use HasFactory, Compoships;

    protected $table = 'opname';

    // Composite Primary Key (Eloquent doesn't support array PKs natively)
    // protected $primaryKey = ['kode_brng', 'tanggal', 'kd_bangsal', 'no_batch', 'no_faktur'];
    protected $primaryKey = null; 
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'h_beli' => 'double',
        'stok' => 'double',
        'real' => 'double',
        'selisih' => 'double',
        'nomihilang' => 'double',
        'lebih' => 'double',
        'nomilebih' => 'double',
    ];

    public function barang()
    {
        return $this->belongsTo(DataBarang::class, 'kode_brng', 'kode_brng');
    }

    public function bangsal()
    {
        return $this->belongsTo(Bangsal::class, 'kd_bangsal', 'kd_bangsal');
    }
}
