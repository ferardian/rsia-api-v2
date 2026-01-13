<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventaris extends Model
{
    protected $table = 'inventaris';
    protected $primaryKey = 'no_inventaris';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    public function barang()
    {
        return $this->belongsTo(InventarisBarang::class, 'kode_barang', 'kode_barang');
    }

    public function ruang()
    {
        return $this->belongsTo(InventarisRuang::class, 'id_ruang', 'id_ruang');
    }
}
