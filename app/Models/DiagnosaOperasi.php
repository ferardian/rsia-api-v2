<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosaOperasi extends Model
{
    protected $table = 'rsia_diagnosa_operasi';
    
    public $timestamps = false;
    
    public $incrementing = false;
    
    protected $primaryKey = null;
    
    protected $fillable = [
        'no_rawat',
        'diagnosa',
        'kode_paket'
    ];
}
