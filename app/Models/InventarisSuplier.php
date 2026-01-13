<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarisSuplier extends Model
{
    protected $table = 'inventaris_suplier';
    protected $primaryKey = 'kode_suplier';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}
