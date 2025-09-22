<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ICD9_Inacbg extends Model
{
    use HasFactory;
     protected $table = 'icd9_inacbg';

    protected $casts = [
    'code' => 'string',
    ];
}
