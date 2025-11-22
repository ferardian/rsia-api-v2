<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * App\Models\Propinsi
 *
 * @property int $kd_prop
 * @property string $nm_prop
 * @method static \Illuminate\Database\Eloquent\Builder|Propinsi newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Propinsi newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Propinsi query()
 * @method static \Illuminate\Database\Eloquent\Builder|Propinsi whereKdProp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Propinsi whereNmProp($value)
 * @mixin \Eloquent
 */
class Propinsi extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'propinsi';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'kd_prop';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nm_prop'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'kd_prop' => 'integer'
    ];
}