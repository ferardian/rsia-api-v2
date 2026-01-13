<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarisHibah extends Model
{
    protected $table = 'inventaris_hibah';
    protected $primaryKey = 'no_hibah';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    public function detail()
    {
        return $this->hasMany(InventarisDetailHibah::class, 'no_hibah', 'no_hibah');
    }
    
    // Potentially relations to PemberiHibah and Petugas
}
