<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\LegacyUser
 *
 * @property string $id_user
 * @property string $password
 */
class LegacyUser extends Model
{
    protected $connection = 'mysql'; // Default connection
    
    protected $table = 'user';
    
    protected $primaryKey = 'id_user';
    
    protected $keyType = 'string';
    
    public $incrementing = false;
    
    public $timestamps = false;
    
    protected $guarded = [];
    
    protected $hidden = [
        'password',
    ];
}
