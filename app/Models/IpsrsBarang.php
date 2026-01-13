<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpsrsBarang extends Model
{
    use HasFactory;

    protected $table = 'ipsrsbarang';
    protected $primaryKey = 'kode_brng';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false; // No created_at/updated_at in schema

    protected $fillable = [
        'kode_brng',
        'nama_brng',
        'kode_sat',
        'jenis',
        'stok',
        'harga',
        'status'
    ];

    // Relationships
    public function satuan()
    {
        return $this->belongsTo(KodeSatuan::class, 'kode_sat', 'kode_sat');
    }

    public function jenisBarang()
    {
        return $this->belongsTo(IpsrsJenisBarang::class, 'jenis', 'kd_jenis');
    }
}
