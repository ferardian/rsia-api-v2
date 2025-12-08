<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaRole extends Model
{
    use HasFactory;

    protected $table = 'rsia_role';
    protected $primaryKey = 'id_role';
    public $timestamps = true;

    protected $fillable = [
        'nama_role',
        'deskripsi',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function userRoles()
    {
        return $this->hasMany(RsiaUserRole::class, 'id_role');
    }

    public function roleMenus()
    {
        return $this->hasMany(RsiaRoleMenu::class, 'id_role');
    }

    public function menus()
    {
        return $this->belongsToMany(RsiaMenu::class, 'rsia_role_menu', 'id_role', 'id_menu')
            ->withPivot(['can_view', 'can_create', 'can_update', 'can_delete', 'can_export', 'can_import']);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helper
    public function hasPermission($menuId, $permission = 'can_view')
    {
        return $this->roleMenus()
            ->where('id_menu', $menuId)
            ->where($permission, true)
            ->exists();
    }

    public function getUserCount()
    {
        return $this->userRoles()->where('is_active', true)->count();
    }
}