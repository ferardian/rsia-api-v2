<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarisJenis extends Model
{
    protected $table = 'inventaris_jenis';
    protected $primaryKey = 'id_jenis';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}
