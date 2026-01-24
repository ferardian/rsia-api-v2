<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaInmutLab extends Model
{
    use HasFactory;

    protected $table = 'rsia_inmut_lab';

    protected $primaryKey = 'id_inmut';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * Get the registrasi that owns the inmut lab.
     */
    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}
