<?php

namespace App\Http\Requests\Access;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user, 'user_id'),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                Rule::unique('users', 'email')->ignore($user, 'user_id'),
            ],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
            'employee_id' => [
                'nullable',
                'integer',
                'exists:employees,employee_id',
                Rule::unique('users', 'employee_id')->ignore($user, 'user_id'),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['sometimes', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'distinct', 'exists:roles,role_id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Username wajib diisi.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.',
            'username.unique' => 'Username sudah digunakan.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'password.min' => 'Password minimal 8 karakter.',
            'employee_id.exists' => 'Karyawan tidak ditemukan.',
            'employee_id.unique' => 'Karyawan ini sudah terhubung ke akun lain.',
            'role_ids.min' => 'Pilih minimal satu peran.',
            'role_ids.*.exists' => 'Peran tidak ditemukan.',
        ];
    }
}
