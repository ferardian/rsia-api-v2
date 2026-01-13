<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpsrsSuplier extends Model
{
    use HasFactory;

    protected $table = 'ipsrssuplier';
    protected $primaryKey = 'kode_suplier';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false; // Schema doesn't have timestamps

    protected $fillable = [
        'kode_suplier',
        'nama_suplier',
        'alamat',
        'kota',
        'no_telp',
        'nama_bank',
        'rekening'
    ];
}
