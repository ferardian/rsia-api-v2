<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaRoleMenu extends Model
{
    use HasFactory;

    protected $table = 'rsia_role_menu';
    protected $primaryKey = 'id_role_menu';
    public $timestamps = true;

    protected $fillable = [
        'id_role',
        'id_menu',
        'can_view',
        'can_create',
        'can_update',
        'can_delete',
        'can_export',
        'can_import',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'id_role' => 'integer',
        'id_menu' => 'integer',
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'can_export' => 'boolean',
        'can_import' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function role()
    {
        return $this->belongsTo(RsiaRole::class, 'id_role');
    }

    public function menu()
    {
        return $this->belongsTo(RsiaMenu::class, 'id_menu');
    }

    // Scopes
    public function scopeWithPermission($query, $permission)
    {
        return $query->where($permission, true);
    }

    public function scopeByRole($query, $roleId)
    {
        return $query->where('id_role', $roleId);
    }

    public function scopeByMenu($query, $menuId)
    {
        return $query->where('id_menu', $menuId);
    }

    // Helper Methods
    public function hasAnyPermission()
    {
        return $this->can_view || $this->can_create || $this->can_update ||
               $this->can_delete || $this->can_export || $this->can_import;
    }

    public function getPermissionArray()
    {
        return [
            'can_view' => $this->can_view,
            'can_create' => $this->can_create,
            'can_update' => $this->can_update,
            'can_delete' => $this->can_delete,
            'can_export' => $this->can_export,
            'can_import' => $this->can_import
        ];
    }

    public static function getDefaultPermissions()
    {
        return [
            'can_view' => true,
            'can_create' => false,
            'can_update' => false,
            'can_delete' => false,
            'can_export' => false,
            'can_import' => false
        ];
    }
}