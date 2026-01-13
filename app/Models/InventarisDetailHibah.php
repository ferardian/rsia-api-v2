<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarisDetailHibah extends Model
{
    protected $table = 'inventaris_detail_hibah';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('no_hibah', '=', $this->getAttribute('no_hibah'))
            ->where('kode_barang', '=', $this->getAttribute('kode_barang'));
        return $query;
    }

    public function hibah()
    {
        return $this->belongsTo(InventarisHibah::class, 'no_hibah', 'no_hibah');
    }

    public function barang()
    {
        return $this->belongsTo(InventarisBarang::class, 'kode_barang', 'kode_barang');
    }
}
