<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ICD10_IDRG extends Model
{
    use HasFactory;
    protected $table = 'icd10_im';

    protected $casts = [
    'code' => 'string',
    ];
}
