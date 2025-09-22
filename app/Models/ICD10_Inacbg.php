<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ICD10_Inacbg extends Model
{
    use HasFactory;
    protected $table = 'icd10_inacbg';

    protected $casts = [
    'code' => 'string',
    ];
}
