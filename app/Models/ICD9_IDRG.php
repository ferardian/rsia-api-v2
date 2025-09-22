<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ICD9_IDRG extends Model
{
    use HasFactory;

    protected $table = 'icd9_im';

    protected $casts = [
    'code' => 'string',
    ];
}
