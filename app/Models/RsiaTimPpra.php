<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaTimPpra extends Model
{
    protected $table = 'rsia_tim_ppra';

    protected $guarded = ['id'];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nik', 'nik');
    }
}
