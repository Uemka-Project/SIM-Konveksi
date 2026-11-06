<?php

namespace App\Http\Requests\Master;

use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaterialRequest extends FormRequest
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
        /** @var Material|null $material */
        $material = $this->route('material');

        return [
            'material_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('materials', 'material_name')->ignore($material, 'material_id'),
            ],
            'unit_name' => ['required', 'string', 'max:20'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'material_name.required' => 'Nama bahan wajib diisi.',
            'material_name.unique' => 'Nama bahan sudah digunakan.',
            'unit_name.required' => 'Satuan wajib diisi.',
            'minimum_stock.required' => 'Stok minimum wajib diisi.',
            'minimum_stock.numeric' => 'Stok minimum harus berupa angka.',
            'minimum_stock.min' => 'Stok minimum tidak boleh negatif.',
        ];
    }
}
