<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarisKategori extends Model
{
    protected $table = 'inventaris_kategori';
    protected $primaryKey = 'id_kategori';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}
