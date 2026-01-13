<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarisBarang extends Model
{
    protected $table = 'inventaris_barang';
    protected $primaryKey = 'kode_barang';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    public function produsen()
    {
        return $this->belongsTo(InventarisProdusen::class, 'kode_produsen', 'kode_produsen');
    }

    public function merk()
    {
        return $this->belongsTo(InventarisMerk::class, 'id_merk', 'id_merk');
    }

    public function kategori()
    {
        return $this->belongsTo(InventarisKategori::class, 'id_kategori', 'id_kategori');
    }

    public function jenis()
    {
        return $this->belongsTo(InventarisJenis::class, 'id_jenis', 'id_jenis');
    }
}
