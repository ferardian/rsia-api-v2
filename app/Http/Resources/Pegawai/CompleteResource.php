<?php

namespace App\Http\Resources\Pegawai;

use Illuminate\Http\Resources\Json\JsonResource;

class CompleteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $data = parent::toArray($request);

        // Explicitly include id and nik field (nik is usually visible, but id is hidden in model)
        $data['id'] = $this->id ?? ($data['id'] ?? null);
        $data['nik'] = $this->nik ?? ($data['nik'] ?? null);
        $data['no_ktp'] = $this->no_ktp ?? ($data['no_ktp'] ?? null);

        return $data;
    }
}
