<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresensiOnline extends Model
{
    protected $table = 'presensi_online';
    protected $fillable = [
        'nik',
        'type',
        'timestamp',
        'photo_path',
        'latitude',
        'longitude',
        'accuracy',
        'face_confidence',
        'liveness_passed',
        'liveness_data',
        'device_info'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'liveness_passed' => 'boolean',
        'liveness_data' => 'array',
        'face_confidence' => 'float',
        'accuracy' => 'decimal:2',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nik', 'nik');
    }
}
