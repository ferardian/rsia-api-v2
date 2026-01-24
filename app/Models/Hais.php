<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hais extends Model
{
    use HasFactory;

    protected $table = 'rsia_hais';

    /**
     * Primary key is composite (tanggal, no_rawat), but Eloquent doesn't support it natively.
     * We'll use 'id' as primary key if available and if we need simple CRUD, 
     * but the schema shows (tanggal, no_rawat) as PK.
     * However, there is a unique auto-increment field 'id'.
     * We'll use 'id' as the primary key for easier CRUD in Eloquent.
     */
    protected $primaryKey = 'id';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * Get the registration associated with the HAIS record.
     */
    public function regPeriksa()
    {
        return $this->belongsTo(RegPeriksa::class, 'no_rawat', 'no_rawat');
    }
}
