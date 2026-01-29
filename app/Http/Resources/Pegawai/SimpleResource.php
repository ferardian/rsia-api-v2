<?php

namespace App\Http\Resources\Pegawai;

use Illuminate\Http\Resources\Json\JsonResource;

class SimpleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if ($this->resource instanceof \App\Models\Pegawai) {
            $pegawai = $this->resource;
            // Eager load if not loaded
            if (!$pegawai->relationLoaded('email')) {
                $pegawai->load('email:nik,email');
            }
            if (!$pegawai->relationLoaded('petugas')) {
                $pegawai->load('petugas:nip,no_telp');
            }
        } else {
            $pegawai = \App\Models\Pegawai::with(['email:nik,email', 'petugas:nip,no_telp'])
                ->select('nik', 'nama', 'alamat', 'jk', 'jbtn', 'departemen', 'no_ktp', 'tmp_lahir', 'tgl_lahir')
                ->where('nik', $this->resource)
                ->first();
        }

        if (!$pegawai) {
            return [];
        }

        return [
            'nik' => $pegawai->nik,
            'nama' => $pegawai->nama,
            'alamat' => $pegawai->alamat,
            'jk' => $pegawai->jk,
            'jbtn' => $pegawai->jbtn,
            'departemen' => $pegawai->departemen,
            'no_ktp' => $pegawai->no_ktp,
            'tmp_lahir' => $pegawai->tmp_lahir,
            'tgl_lahir' => $pegawai->tgl_lahir,
            'no_telp' => $pegawai->petugas->no_telp ?? \App\Models\Petugas::where('nip', $pegawai->nik)->value('no_telp'),
            'email_resmi' => $pegawai->email->email ?? null
        ];
    }
}
