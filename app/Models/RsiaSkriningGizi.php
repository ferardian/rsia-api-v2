<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaSkriningGizi extends Model
{
    protected $table = 'rsia_skrining_gizi';
    protected $primaryKey = 'no_rawat';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'bb' => 'double',
        'tb' => 'double',
        'lila' => 'double',
        'skor' => 'integer',
        'hb' => 'double'
    ];

    // Relationship
    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function kamarInap()
    {
        return $this->belongsTo(KamarInap::class, 'no_rawat', 'no_rawat');
    }
}
