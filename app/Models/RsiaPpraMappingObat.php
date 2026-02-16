<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaPpraMappingObat extends Model
{
    protected $table = 'rsia_ppra_mapping_obat';
 
    protected $fillable = ['kode_brng', 'rute_pemberian', 'nilai_ddd_who', 'status_notif'];

    public function barang()
    {
        return $this->belongsTo(DataBarang::class, 'kode_brng', 'kode_brng');
    }
}
