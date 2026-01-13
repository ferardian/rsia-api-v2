<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Jenis
 *
 * @property string $kdjns
 * @property string $nama
 * @property string $keterangan
 * @method static \Illuminate\Database\Eloquent\Builder|Jenis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Jenis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Jenis query()
 * @method static \Illuminate\Database\Eloquent\Builder|Jenis whereKdjns($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Jenis whereNama($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Jenis whereKeterangan($value)
 * @mixin \Eloquent
 */
class Jenis extends Model
{
    use HasFactory;

    protected $table = 'jenis';

    protected $primaryKey = 'kdjns';

    protected $guarded = [];

    public $incrementing = false;

    public $timestamps = false;

    public function dataBarangs()
    {
        return $this->hasMany(DataBarang::class, 'kdjns', 'kdjns');
    }
}