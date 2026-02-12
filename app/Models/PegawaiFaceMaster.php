<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PegawaiFaceMaster extends Model
{
    use HasFactory;

    protected $table = 'rsia_pegawai_face_master';
    public $timestamps = false;

    protected $fillable = [
        'pegawai_id', 'nik', 'photo_path', 
        'face_encoding', 'registered_at', 'updated_at', 'is_active'
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id', 'id');
    }
}
