<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaSlider extends Model
{
    use HasFactory;

    protected $table = 'rsia_slider';

    protected $guarded = [];

    protected $primaryKey = 'id';
}
