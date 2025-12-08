<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RsiaPetugas extends Model
{
    use HasFactory;

    protected $table = 'rsia_petugas';
    protected $primaryKey = 'nip';
    public $timestamps = true;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'nip',
        'nama',
        'jk',
        'tmp_lahir',
        'tgl_lahir',
        'gol_darah',
        'agama',
        'stts_nikah',
        'alamat',
        'kd_jbtn',
        'no_telp',
        'status'
    ];

    protected $casts = [
        'tgl_lahir' => 'date',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'umur',
        'nama_lengkap'
    ];

    // Relationships
    public function jabatan()
    {
        return $this->belongsTo(RsiaJabatan::class, 'kd_jbtn', 'kd_jbtn');
    }

    public function userRoles()
    {
        return $this->hasMany(RsiaUserRole::class, 'nip', 'nip');
    }

    public function activeUserRole()
    {
        return $this->hasOne(RsiaUserRole::class, 'nip', 'nip')
            ->where('is_active', true)
            ->with('role');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeLaki($query)
    {
        return $query->where('jk', 'L');
    }

    public function scopePerempuan($query)
    {
        return $query->where('jk', 'P');
    }

    public function scopeByJabatan($query, $kdJbtn)
    {
        return $query->where('kd_jbtn', $kdJbtn);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('nama', 'like', "%{$search}%")
              ->orWhere('nip', 'like', "%{$search}%")
              ->orWhere('alamat', 'like', "%{$search}%")
              ->orWhere('no_telp', 'like', "%{$search}%");
        });
    }

    // Accessors
    public function getUmurAttribute()
    {
        return $this->tgl_lahir ? $this->tgl_lahir->age : null;
    }

    public function getNamaLengkapAttribute()
    {
        return $this->nama;
    }

    public function getJenisKelaminAttribute()
    {
        return match($this->jk) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => 'Unknown'
        };
    }

    public function getStatusPerkawinanAttribute()
    {
        return match($this->stts_nikah) {
            'SINGLE' => 'Belum Menikah',
            'MENIKAH' => 'Menikah',
            'JANDA' => 'Janda',
            'DUDHA' => 'Duda',
            'JOMBLO' => 'Belum Menikah',
            default => 'Unknown'
        };
    }

    // Helper Methods
    public function getGolonganDarahAttribute()
    {
        return $this->gol_darah === '-' ? 'Tidak diketahui' : $this->gol_darah;
    }

    public function hasRole()
    {
        return $this->activeUserRole()->exists();
    }

    public function getCurrentRole()
    {
        $userRole = $this->activeUserRole()->first();
        return $userRole ? $userRole->role : null;
    }

    public function getRoleName()
    {
        $role = $this->getCurrentRole();
        return $role ? $role->nama_role : 'Tidak ada role';
    }

    public function getDisplayName()
    {
        return $this->nama;
    }

    public function can($permission, $menuId = null)
    {
        $role = $this->getCurrentRole();
        if (!$role) return false;

        if ($menuId) {
            return $role->hasPermission($menuId, $permission);
        }

        // Check if role has any permission for the given permission type
        return $role->roleMenus()
            ->where($permission, true)
            ->exists();
    }
}