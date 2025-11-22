<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RsiaMappingLabResource extends JsonResource
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
            'kd_jenis_prw' => $this->kd_jenis_prw,
            'code' => $this->code,
            'system' => $this->system,
            'display' => $this->display,
            'jenis_perawatan_lab' => $this->whenLoaded('jenisPerawatanLab', function () {
                return [
                    'kd_jenis_prw' => $this->jenisPerawatanLab->kd_jenis_prw,
                    'nm_perawatan' => $this->jenisPerawatanLab->nm_perawatan,
                    'bagian_rs' => $this->jenisPerawatanLab->bagian_rs,
                    'bhp' => $this->jenisPerawatanLab->bhp,
                    'tarif_perujuk' => $this->jenisPerawatanLab->tarif_perujuk,
                    'tarif_tindakan_dokter' => $this->jenisPerawatanLab->tarif_tindakan_dokter,
                    'tarif_tindakan_paramedis' => $this->jenisPerawatanLab->tarif_tindakan_paramedis,
                    'tarif_konsultan' => $this->jenisPerawatanLab->tarif_konsultan,
                    'tarif_total' => $this->jenisPerawatanLab->tarif_total,
                    'kd_pj' => $this->jenisPerawatanLab->kd_pj,
                    'status' => $this->jenisPerawatanLab->status,
                ];
            }),
            'created_at' => $this->when(isset($this->created_at), $this->created_at),
            'updated_at' => $this->when(isset($this->updated_at), $this->updated_at),
        ];
    }
}