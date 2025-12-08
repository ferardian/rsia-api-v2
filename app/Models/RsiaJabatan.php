<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaJabatan extends Model
{
    use HasFactory;

    protected $table = 'rsia_jabatan';
    protected $primaryKey = 'kd_jbtn';
    public $timestamps = true;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'kd_jbtn',
        'nama_jbtn'
    ];

    // Relationships
    public function petugas()
    {
        return $this->hasMany(RsiaPetugas::class, 'kd_jbtn', 'kd_jbtn');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereHas('petugas', function($q) {
            $q->where('status', 1);
        });
    }

    // Helper Methods
    public function getPetugasCount()
    {
        return $this->petugas()->active()->count();
    }

    public function isSupervisor()
    {
        $supervisorRoles = ['admin', 'koordinator', 'manager', 'kepala'];
        return collect($supervisorRoles)->contains(function($role) {
            return stripos($this->nama_jbtn, $role) !== false;
        });
    }
}