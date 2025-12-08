<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RsiaMappingProcedureInapResource extends JsonResource
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
            'description' => $this->description,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'jenis_perawatan_inap' => $this->whenLoaded('jenisPerawatanInap', function () {
                return [
                    'kd_jenis_prw' => $this->jenisPerawatanInap->kd_jenis_prw,
                    'nm_perawatan' => $this->jenisPerawatanInap->nm_perawatan,
                    'kd_poli' => $this->jenisPerawatanInap->kd_poli,
                    'kd_pj' => $this->jenisPerawatanInap->kd_pj,
                    'status' => $this->jenisPerawatanInap->status,
                    'kategori' => $this->jenisPerawatanInap->kategori,
                ];
            }),
        ];
    }
}