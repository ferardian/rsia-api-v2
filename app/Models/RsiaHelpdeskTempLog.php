<?php
/**
 * Created by Antigravity.
 * User: Ferry Ardiansyah
 * Date: 2026-01-15
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaHelpdeskTempLog extends Model
{
    protected $table = 'rsia_helpdesk_temp_log';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nomor_wa',
        'nik_pelapor',
        'kd_dep',
        'isi_laporan',
        'raw_message',
        'status'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nik_pelapor', 'nik');
    }

    public function departemen()
    {
        return $this->belongsTo(Departemen::class, 'kd_dep', 'dep_id');
    }
}
