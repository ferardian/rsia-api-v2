<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AplicareKetersediaanKamar extends Model
{
    protected $table = 'aplicare_ketersediaan_kamar';
    public $timestamps = false;

    // Composite primary key
    protected $primaryKey = ['kode_kelas_aplicare', 'kd_bangsal', 'kelas'];
    public $incrementing = false;

    protected $fillable = [
        'kode_kelas_aplicare',
        'kd_bangsal',
        'kelas',
        'kapasitas',
        'tersedia',
        'tersediapria',
        'tersediawanita',
        'tersediapriawanita'
    ];

    protected $casts = [
        'kapasitas' => 'integer',
        'tersedia' => 'integer',
        'tersediapria' => 'integer',
        'tersediawanita' => 'integer',
        'tersediapriawanita' => 'integer'
    ];

    /**
     * Get the ward for this bed availability
     */
    public function bangsal()
    {
        return $this->belongsTo(Bangsal::class, 'kd_bangsal', 'kd_bangsal');
    }

    /**
     * Override getKeyName for composite key
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * Get the primary key value for a save query.
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }
}
