<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaFacility extends Model
{
    use HasFactory;

    protected $table = 'rsia_fasilitas';

    protected $guarded = [];

    protected $primaryKey = 'id';
}
