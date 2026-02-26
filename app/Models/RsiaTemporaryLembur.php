<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaTemporaryLembur extends Model
{
    use HasFactory;

    protected $table = 'rsia_temporary_lembur';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'jam_datang', 'jam_pulang', 'durasi', 'photo'
    ];

    protected $casts = [
        'jam_datang' => 'datetime',
        'jam_pulang' => 'datetime',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id', 'id');
    }
}
