<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

class RiwayatBarangMedis extends Model
{
    use HasFactory, Compoships;

    protected $table = 'riwayat_barang_medis';

    // Based on table definition, no single primary key is defined, but standard Eloquent expects one.
    // If there is no PK, we set incrementing to false.
    // However, usually these tables might have composite keys or no keys. 
    // For read-only purposes (history), strictly speaking we don't need a PK if we don't do finds/updates by ID.
    public $incrementing = false;
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'stok_awal' => 'double',
        'masuk' => 'double',
        'keluar' => 'double',
        'stok_akhir' => 'double',
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
