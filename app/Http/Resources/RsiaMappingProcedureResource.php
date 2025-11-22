<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RsiaMappingProcedureResource extends JsonResource
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
            'nm_perawatan' => $this->whenLoaded('jenisPerawatan', function () {
                return $this->jenisPerawatan->nm_perawatan ?? null;
            }),
            'snomed' => [
                'code' => $this->code,
                'system' => $this->system,
                'display' => $this->display,
                'description' => $this->description,
                'formatted' => $this->code && $this->display ? "{$this->code} - {$this->display}" : null,
            ],
            'status' => $this->status,
            'notes' => $this->notes,
            'metadata' => [
                'created_by' => $this->created_by,
                'updated_by' => $this->updated_by,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
            'has_mapping' => !empty($this->code) && !empty($this->display) && $this->status === 'active',
        ];
    }
}
