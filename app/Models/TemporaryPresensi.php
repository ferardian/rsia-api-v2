<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporaryPresensi extends Model
{
    use HasFactory;

    protected $table = 'temporary_presensi';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'shift', 'jam_datang', 'status', 
        'keterlambatan', 'keterangan', 'photo'
    ];

    protected $casts = [
        'jam_datang' => 'datetime',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id', 'id');
    }
}
