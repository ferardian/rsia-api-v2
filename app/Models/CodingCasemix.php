<?php
// app/Models/CodingCasemix.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodingCasemix extends Model
{
    protected $table = 'coding_casemix';
    
    protected $fillable = [
        'no_sep', 'no_rawat', 'tgl_coding', 'koder_nip', 
        'status', 'catatan_koder', 'verified_by', 'verified_date'
    ];

    protected $casts = [
        'tgl_coding' => 'datetime',
        'verified_date' => 'datetime',
    ];

    // Relasi
    public function bridgingSep()
    {
        return $this->belongsTo(BridgingSep::class, 'no_sep', 'no_sep');
    }

    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function clinicalNotesSnomed()
    {
        return $this->hasMany(ClinicalNotesSnomed::class, 'coding_id');
    }

    public function koder()
    {
        return $this->belongsTo(Pegawai::class, 'koder_nip', 'nik');
    }

    public function verifier()
    {
        return $this->belongsTo(Pegawai::class, 'verified_by', 'nik');
    }
}