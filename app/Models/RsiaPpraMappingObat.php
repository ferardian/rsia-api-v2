<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaPpraMappingObat extends Model
{
    protected $table = 'rsia_ppra_mapping_obat';

    protected $guarded = ['id'];

    public function barang()
    {
        return $this->belongsTo(DataBarang::class, 'kode_brng', 'kode_brng');
    }
}
