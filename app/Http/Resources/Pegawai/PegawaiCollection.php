<?php

namespace App\Http\Resources\Pegawai;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PegawaiCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $select = $request->input('select', '*');
        $data = $this->collection->transform(function ($item) use ($select, $request) {

            if ($select == "*") {
                $modifieditem = $item;
            } else {
                $modifieditem = $item->only(explode(',', $select));
            }

            // add dep to item
            if ($request->has('include') && in_array('dep', explode(',', $request->input('include')))) {
                $modifieditem['dep'] = $item->dep;
            }

            // Add role data from LEFT JOIN (already available from Orion controller)
            $modifieditem['id_role'] = $item['role_id'] ? (int) $item['role_id'] : null;
            $modifieditem['role_name'] = $item['role_name'] ?: 'Belum ada role';

            // Add frontend-specific fields
            $modifieditem['id_user'] = $item['nik']; // frontend expects id_user
            $modifieditem['username'] = $item['nik']; // fallback to nik
            $modifieditem['status'] = ($item['stts_aktif'] ?? '') === 'AKTIF' ? 1 : 0;

            return $modifieditem;
        });

        return $data;
    }
}
