<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarisRuang extends Model
{
    protected $table = 'inventaris_ruang';
    protected $primaryKey = 'id_ruang';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}
