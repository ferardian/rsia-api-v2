<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsiaUnitShiftRule extends Model
{
    protected $table = 'rsia_unit_shift_rules';
    
    protected $fillable = [
        'dep_id',
        'shift_code',
        'duration_hours',
        'priority',
        'min_staff',
        'role_type',
    ];

    protected $casts = [
        'duration_hours' => 'decimal:2',
        'priority' => 'integer',
        'min_staff' => 'integer',
    ];

    public $timestamps = false;

    /**
     * Relationship to Departemen
     */
    public function departemen()
    {
        return $this->belongsTo(Departemen::class, 'dep_id', 'dep_id');
    }
}
