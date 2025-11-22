<?php
// app/Models/ClinicalNotesSnomed.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalNotesSnomed extends Model
{
    protected $table = 'clinical_notes_snomed';
    
    protected $fillable = [
        'coding_id', 'no_rawat', 'tgl_perawatan', 'jam_rawat',
        'source_field', 'source_text',
        'snomed_concept_id', 'snomed_term', 'snomed_fsn',
        'icd10_code', 'icd10_description',
        'concept_type', 'confidence_score', 'mapped_by'
    ];

    protected $casts = [
        'tgl_perawatan' => 'date',
        'confidence_score' => 'decimal:2'
    ];

    public $timestamps = false; // Only created_at

    // Relasi
    public function codingCasemix()
    {
        return $this->belongsTo(CodingCasemix::class, 'coding_id');
    }

    public function pemeriksaanRalan()
    {
        return $this->belongsTo(PemeriksaanRalan::class, ['no_rawat', 'tgl_perawatan', 'jam_rawat'], ['no_rawat', 'tgl_perawatan', 'jam_rawat']);
    }
}