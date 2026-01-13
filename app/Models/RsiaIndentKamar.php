<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaIndentKamar extends Model
{
    use HasFactory;

    protected $table = 'rsia_indent_kamar';

    protected $primaryKey = 'kd_indent';

    protected $fillable = ['kd_kamar', 'pasien', 'tanggal_input'];

    public $timestamps = false;

    public function kamar()
    {
        return $this->belongsTo(Kamar::class, 'kd_kamar', 'kd_kamar');
    }
}
