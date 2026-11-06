<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'position' => ['required', 'string', 'max:50'],
            'active_status' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama karyawan wajib diisi.',
            'name.max' => 'Nama karyawan maksimal 100 karakter.',
            'phone.max' => 'Nomor telepon maksimal 20 karakter.',
            'position.required' => 'Jabatan wajib diisi.',
            'position.max' => 'Jabatan maksimal 50 karakter.',
        ];
    }
}
