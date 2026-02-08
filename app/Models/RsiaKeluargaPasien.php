<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaKeluargaPasien extends Model
{
    use HasFactory;

    protected $table = 'rsia_keluarga_pasien';

    protected $guarded = [];

    /**
     * Get the master patient that owns the family member.
     */
    public function master()
    {
        return $this->belongsTo(Pasien::class, 'no_rkm_medis_master', 'no_rkm_medis');
    }

    /**
     * Get the family member patient.
     */
    public function keluarga()
    {
        return $this->belongsTo(Pasien::class, 'no_rkm_medis_keluarga', 'no_rkm_medis');
    }
}
