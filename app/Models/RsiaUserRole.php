<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaUserRole extends Model
{
    use HasFactory;

    protected $table = 'rsia_user_role';
    protected $primaryKey = 'id_user_role';
    public $timestamps = true;

    protected $fillable = [
        'id_user',
        'id_role',
        'nip',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'id_role' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function role()
    {
        return $this->belongsTo(RsiaRole::class, 'id_role');
    }

    public function petugas()
    {
        return $this->belongsTo(RsiaPetugas::class, 'nip', 'nip');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('id_user', $userId);
    }

    public function scopeByPetugas($query, $nip)
    {
        return $query->where('nip', $nip);
    }

    // Helper Methods
    public function getUserMenus()
    {
        return $this->role->menus()
            ->wherePivot('can_view', true)
            ->where('is_active', true)
            ->with(['children' => function($query) {
                $query->where('is_active', true);
            }])
            ->get();
    }

    public function hasPermission($menuId, $permission = 'can_view')
    {
        return $this->role->roleMenus()
            ->where('id_menu', $menuId)
            ->where($permission, true)
            ->exists();
    }

    public function getFullName()
    {
        return $this->petugas ? $this->petugas->nama : 'Unknown';
    }

    public function getRoleName()
    {
        return $this->role ? $this->role->nama_role : 'Unknown';
    }
}