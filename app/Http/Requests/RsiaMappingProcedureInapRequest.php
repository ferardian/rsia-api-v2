<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RsiaMappingProcedureInapRequest extends FormRequest
{
    /**
     * Determine if user is authorized to make this request.
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
        $rules = [
            'code' => 'nullable|string|max:20',
            'system' => 'required|string|max:50',
            'display' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive,draft',
            'notes' => 'nullable|string|max:500',
            'created_by' => 'nullable|string|max:50',
            'updated_by' => 'nullable|string|max:50',
        ];

        // For POST (store) - kd_jenis_prw is required
        if ($this->isMethod('POST')) {
            $rules['kd_jenis_prw'] = 'required|string|max:15|exists:jns_perawatan_inap,kd_jenis_prw';
            // If code is provided, display is also required and vice versa
            $rules['code'] = 'required_without:display|string|max:20';
            $rules['display'] = 'required_without:code|string|max:255';
        }

        // For PUT/PATCH (update) - kd_jenis_prw is in route parameter
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            // Optional for update
            $rules['code'] = 'sometimes|required_without:display|string|max:20';
            $rules['display'] = 'sometimes|required_without:code|string|max:255';
        }

        return $rules;
    }

    /**
     * Get the custom error messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'kd_jenis_prw.required' => 'Kode jenis perawatan rawat inap wajib diisi',
            'kd_jenis_prw.exists' => 'Kode jenis perawatan rawat inap tidak valid',
            'code.required_without' => 'Kode SNOMED wajib diisi jika display name tidak diisi',
            'display.required_without' => 'Display name wajib diisi jika kode SNOMED tidak diisi',
            'code.max' => 'Kode SNOMED maksimal 20 karakter',
            'display.max' => 'Display name maksimal 255 karakter',
            'description.max' => 'Deskripsi maksimal 1000 karakter',
            'status.required' => 'Status wajib dipilih',
            'status.in' => 'Status harus salah satu dari: active, inactive, draft',
            'system.required' => 'System URL wajib diisi',
            'notes.max' => 'Catatan maksimal 500 karakter',
        ];
    }

    /**
     * Get the custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'kd_jenis_prw' => 'Kode Jenis Perawatan Rawat Inap',
            'code' => 'Kode SNOMED',
            'system' => 'System URL',
            'display' => 'Display Name',
            'description' => 'Deskripsi',
            'status' => 'Status',
            'notes' => 'Catatan',
            'created_by' => 'Dibuat Oleh',
            'updated_by' => 'Diperbarui Oleh',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'system' => $this->system ?? 'http://snomed.info/sct',
            'status' => $this->status ?? 'active',
        ]);
    }
}