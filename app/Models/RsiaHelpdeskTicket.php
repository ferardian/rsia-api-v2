<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaHelpdeskTicket extends Model
{
    protected $table = 'rsia_helpdesk_tickets';
    protected $primaryKey = 'id';
    public $timestamps = true;

    const CREATED_AT = 'tanggal';
    const UPDATED_AT = null; // Assuming no updated_at column in this legacy table

    protected $fillable = [
        'no_tiket',
        'tanggal',
        'nik_pelapor',
        'dep_id',
        'keluhan',
        'prioritas',
        'status',
        'nik_teknisi',
        'solusi',
        'jam_mulai',
        'jam_selesai'
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'jam_mulai' => 'datetime',
        'jam_selesai' => 'datetime',
    ];

    public function pelapor()
    {
        return $this->belongsTo(Pegawai::class, 'nik_pelapor', 'nik');
    }

    public function teknisi()
    {
        return $this->belongsTo(Pegawai::class, 'nik_teknisi', 'nik');
    }

    public function departemen()
    {
        return $this->belongsTo(Departemen::class, 'dep_id', 'dep_id');
    }
}
