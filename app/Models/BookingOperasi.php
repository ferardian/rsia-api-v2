<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Awobaz\Compoships\Compoships;

class BookingOperasi extends Model
{
    use Compoships;
    protected $table = 'booking_operasi';
    protected $primaryKey = 'no_rawat'; // Composite key actually, but Eloquent needs one. set incrementing false
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'no_rawat',
        'kode_paket',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'status',
        'kd_dokter'
    ];

    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }

    public function paketOperasi()
    {
        return $this->belongsTo(PaketOperasi::class, 'kode_paket', 'kode_paket');
    }

    public function dokter()
    {
        return $this->belongsTo(Dokter::class, 'kd_dokter', 'kd_dokter');
    }

    public function diagnosaOperasi()
    {
        return $this->hasMany(DiagnosaOperasi::class, 'no_rawat', 'no_rawat');
    }

    public function laporanOperasi()
    {
        return $this->hasOne(RsiaOperasiSafe::class, 
            ['no_rawat', 'kode_paket'], 
            ['no_rawat', 'kode_paket']
        );
    }
}
