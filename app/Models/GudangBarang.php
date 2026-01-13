<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

class GudangBarang extends Model
{
    use HasFactory, Compoships;

    protected $table = 'gudangbarang';

    // Composite Primary Key
    protected $primaryKey = ['kode_brng', 'kd_bangsal', 'no_batch', 'no_faktur'];
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'stok' => 'double',
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
