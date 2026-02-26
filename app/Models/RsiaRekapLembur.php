<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaRekapLembur extends Model
{
    use HasFactory;

    protected $table = 'rsia_rekap_lembur';
    // Using id as primary key for Laravel compatibility, though it's composite in DB
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id', 'jam_datang', 'jam_pulang', 'durasi', 
        'durasi_pengajuan', 'durasi_acc', 'photo', 
        'kegiatan', 'status'
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
