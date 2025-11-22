<?php

namespace App\Http\Resources\Pegawai;

use Illuminate\Http\Resources\Json\JsonResource;

class PegawaiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Get all fields from model + ensure no_ktp is included
        $data = parent::toArray($request);

        // Explicitly include no_ktp field
        $data['no_ktp'] = $this->no_ktp ?? null;

        return $data;
    }
}
