<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndustriFarmasi extends Model
{
    use HasFactory;

    protected $table = 'industrifarmasi';

    protected $primaryKey = 'kode_industri';

    protected $guarded = [];

    public $incrementing = false;

    public $timestamps = false;
}
