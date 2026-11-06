<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class BomRequest extends FormRequest
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
            'items' => ['present', 'array'],
            'items.*.material_id' => ['required', 'integer', 'distinct', 'exists:materials,material_id'],
            'items.*.quantity_per_unit' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.present' => 'Daftar bahan wajib dikirim.',
            'items.array' => 'Daftar bahan tidak valid.',
            'items.*.material_id.required' => 'Bahan wajib dipilih.',
            'items.*.material_id.distinct' => 'Bahan tidak boleh dipilih lebih dari sekali.',
            'items.*.material_id.exists' => 'Bahan tidak ditemukan.',
            'items.*.quantity_per_unit.required' => 'Kebutuhan per unit wajib diisi.',
            'items.*.quantity_per_unit.numeric' => 'Kebutuhan per unit harus berupa angka.',
            'items.*.quantity_per_unit.gt' => 'Kebutuhan per unit harus lebih dari 0.',
        ];
    }
}
