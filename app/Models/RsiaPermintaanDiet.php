<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaPermintaanDiet extends Model
{
    use HasFactory;

    protected $table = 'rsia_permintaan_diet';

    protected $primaryKey = ['no_rawat', 'tanggal'];

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date',
    ];

    /**
     * Get the regPeriksa that owns the diet request
     */
    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}
