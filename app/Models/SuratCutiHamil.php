<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuratCutiHamil extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'surat_cuti_hamil';

    /**
     * The primary key associated with the table.
     * 
     * @var string
     */
    protected $primaryKey = 'no_rawat';

    /**
     * The primary key associated with the table.
     * 
     * @var string
     * 
     */
    protected $guarded = [];

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
    public $incrementing = false;

    /**
     * Get the reg_periksa that owns the SuratCutiHamil
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}
