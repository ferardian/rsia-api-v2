<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProcedureBillingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'no_rawat' => $this->no_rawat,
            'kd_jenis_prw' => $this->kd_jenis_prw,
            'nm_perawatan' => $this->nm_perawatan,
            'status_rawat' => $this->status_rawat,
            'jenis_petugas' => $this->jenis_petugas,
            'kd_dokter' => $this->kd_dokter,
            'dokter' => $this->whenLoaded('dokter', function () {
                return [
                    'kd_dokter' => $this->dokter->kd_dokter,
                    'nm_dokter' => $this->dokter->nm_dokter,
                    'jk' => $this->dokter->jk,
                    'spesialis' => $this->dokter->spesialis,
                ];
            }),
            'nip' => $this->nip,
            'petugas' => $this->whenLoaded('petugas', function () {
                return [
                    'nip' => $this->petugas->nip,
                    'nama' => $this->petugas->nama,
                    'jk' => $this->petugas->jk,
                ];
            }),
            'nama_petugas' => $this->nama_petugas,
            'biaya' => [
                'material' => (float) $this->material,
                'bhp' => (float) $this->bhp,
                'tarif_tindakandr' => (float) $this->tarif_tindakandr,
                'tarif_tindakanpr' => (float) $this->tarif_tindakanpr,
                'kso' => (float) $this->kso,
                'menejemen' => (float) $this->menejemen,
                'total_biaya' => $this->total_biaya,
                'biaya_rawat' => (float) $this->biaya_rawat,
            ],
            'waktu' => [
                'tgl_perawatan' => $this->tgl_perawatan,
                'jam_rawat' => $this->jam_rawat,
            ],
            'status_bayar' => $this->stts_bayar,
        ];
    }
}