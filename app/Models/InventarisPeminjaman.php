<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class InventarisPeminjaman extends Model
{
    protected $table = 'inventaris_peminjaman';
    // Composite keys are not natively supported by Eloquent for save/update without workarounds.
    // We disable auto increment and manage keys manually.
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    // Since Laravel doesn't support composite primary keys well, we might need to override
    // setKeysForSaveQuery if we intend to use save() on existing models effectively.
    // For now, simpler querying and inserting via query builder might be preferred for complex operations.
    
    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('peminjam', '=', $this->getAttribute('peminjam'))
            ->where('no_inventaris', '=', $this->getAttribute('no_inventaris'))
            ->where('tgl_pinjam', '=', $this->getAttribute('tgl_pinjam'))
            ->where('nip', '=', $this->getAttribute('nip'));

        return $query;
    }

    public function inventaris()
    {
        return $this->belongsTo(Inventaris::class, 'no_inventaris', 'no_inventaris');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'nip', 'nik'); // Assuming Pegawai uses 'nik' as primary key based on usual RSIA schema, though here column is 'nip'. Check if it maps to 'nik'.
    }
}
