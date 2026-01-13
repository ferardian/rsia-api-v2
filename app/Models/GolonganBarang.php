<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GolonganBarang extends Model
{
    use HasFactory;

    protected $table = 'golongan_barang';

    protected $primaryKey = 'kode';

    protected $guarded = [];

    public $incrementing = false;

    public $timestamps = false;
}
