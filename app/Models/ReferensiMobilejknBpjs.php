<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferensiMobilejknBpjs extends Model
{
    protected $table = 'referensi_mobilejkn_bpjs';
    protected $primaryKey = 'nobooking';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $guarded = [];

    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}
