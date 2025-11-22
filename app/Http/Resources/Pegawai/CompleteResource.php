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
        $data = $this->resource;

        // Ensure no_ktp is included even if resource is already array
        if (is_object($data)) {
            $data = $data->toArray();
        }

        // Explicitly include no_ktp field
        $data['no_ktp'] = $this->no_ktp ?? ($data['no_ktp'] ?? null);

        return $data;
    }
}
