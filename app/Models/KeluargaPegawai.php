<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeluargaPegawai extends Model
{
    use HasFactory;

    protected $table = 'rsia_keluarga_pegawai';

    protected $guarded = ['id'];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nik', 'nik');
    }
}
