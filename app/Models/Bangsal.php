<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bangsal extends Model
{
    use HasFactory;

    protected $table = 'bangsal';

    protected $primaryKey = 'kd_bangsal';

    protected $guarded = [];

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * Get all rooms in this ward
     */
    public function kamar()
    {
        return $this->hasMany(Kamar::class, 'kd_bangsal', 'kd_bangsal');
    }

    /**
     * Get bed availability for this ward
     */
    public function ketersediaanKamar()
    {
        return $this->hasMany(AplicareKetersediaanKamar::class, 'kd_bangsal', 'kd_bangsal');
    }

    /**
     * Scope to get only active wards
     */
    public function scopeActive($query)
    {
        return $query->where('status', '1');
    }
}
