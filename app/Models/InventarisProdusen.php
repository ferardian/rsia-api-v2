<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarisProdusen extends Model
{
    protected $table = 'inventaris_produsen';
    protected $primaryKey = 'kode_produsen';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}
