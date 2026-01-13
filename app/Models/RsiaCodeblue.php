<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaCodeblue extends Model
{
    protected $table = 'rsia_codeblue';
    protected $primaryKey = ['tanggal', 'tim'];
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'tanggal',
        'tim',
        'pagi',
        'siang',
        'malam',
        'no_urut',
        'status'
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // Relationship to Pegawai for Pagi shift
    public function pegawaiPagi()
    {
        return $this->belongsTo(Pegawai::class, 'pagi', 'nik');
    }

    // Relationship to Pegawai for Siang shift
    public function pegawaiSiang()
    {
        return $this->belongsTo(Pegawai::class, 'siang', 'nik');
    }

    // Relationship to Pegawai for Malam shift
    public function pegawaiMalam()
    {
        return $this->belongsTo(Pegawai::class, 'malam', 'nik');
    }
}
