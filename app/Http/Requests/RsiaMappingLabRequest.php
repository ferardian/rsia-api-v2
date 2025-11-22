<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RsiaMappingLabRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $mappingLab = $this->route('rsia_mapping_lab');

        return [
            'kd_jenis_prw' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'exists:jns_perawatan_lab,kd_jenis_prw',
                $isUpdate ? Rule::unique('rsia_mapping_lab')->ignore($mappingLab?->kd_jenis_prw, 'kd_jenis_prw') : 'unique:rsia_mapping_lab,kd_jenis_prw'
            ],
            'code' => 'nullable|string|max:15',
            'system' => 'required|string|max:100',
            'display' => 'nullable|string|max:80',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'kd_jenis_prw.required' => 'Kode jenis perawatan harus diisi',
            'kd_jenis_prw.exists' => 'Jenis perawatan lab tidak ditemukan',
            'kd_jenis_prw.unique' => 'Mapping lab untuk jenis perawatan ini sudah ada',
            'code.max' => 'Code maksimal 15 karakter',
            'system.required' => 'System harus diisi',
            'system.max' => 'System maksimal 100 karakter',
            'display.max' => 'Display maksimal 80 karakter',
        ];
    }
}