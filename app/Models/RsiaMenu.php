<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaMenu extends Model
{
    use HasFactory;

    protected $table = 'rsia_menu';
    protected $primaryKey = 'id_menu';
    public $timestamps = true;

    protected $fillable = [
        'nama_menu',
        'icon',
        'route',
        'parent_id',
        'urutan',
        'is_active',
        'platform',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'urutan' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function children()
    {
        return $this->hasMany(RsiaMenu::class, 'parent_id')
            ->orderBy('urutan');
    }

    public function parent()
    {
        return $this->belongsTo(RsiaMenu::class, 'parent_id');
    }

    public function roleMenus()
    {
        return $this->hasMany(RsiaRoleMenu::class, 'id_menu');
    }

    public function roles()
    {
        return $this->belongsToMany(RsiaRole::class, 'rsia_role_menu', 'id_menu', 'id_role')
            ->withPivot(['can_view', 'can_create', 'can_update', 'can_delete', 'can_export', 'can_import']);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('parent_id')->orderBy('urutan');
    }

    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    // Helper Methods
    public function isParent()
    {
        return is_null($this->parent_id);
    }

    public function hasChildren()
    {
        return $this->children()->exists();
    }

    public function getFullPath()
    {
        $path = [];
        $current = $this;

        while ($current) {
            array_unshift($path, $current->nama_menu);
            $current = $current->parent;
        }

        return implode(' > ', $path);
    }

    // Get menu tree with children
    public static function getMenuTree($onlyActive = true)
    {
        $query = self::with(['children' => function($query) use ($onlyActive) {
            if ($onlyActive) {
                $query->active();
            }
            $query->ordered();
        }]);

        if ($onlyActive) {
            $query->active();
        }

        return $query->parents()->ordered()->get();
    }

    // Get flat menu list for admin
    public static function getFlatList($onlyActive = true)
    {
        $query = self::select('id_menu', 'nama_menu', 'parent_id', 'urutan', 'is_active');

        if ($onlyActive) {
            $query->active();
        }

        return $query->ordered()->get();
    }
}