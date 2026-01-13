<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarisMerk extends Model
{
    protected $table = 'inventaris_merk';
    protected $primaryKey = 'id_merk';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}
