<?php

namespace App\Http\Requests\Master;

use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductVariantRequest extends FormRequest
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
        /** @var ProductVariant|null $variant */
        $variant = $this->route('variant');

        return [
            'size' => ['required', 'string', 'max:10'],
            'color' => ['required', 'string', 'max:30'],
            'sku' => [
                'required',
                'string',
                'max:30',
                Rule::unique('product_variants', 'sku')->ignore($variant, 'variant_id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'size.required' => 'Ukuran wajib diisi.',
            'color.required' => 'Warna wajib diisi.',
            'sku.required' => 'SKU wajib diisi.',
            'sku.unique' => 'SKU sudah digunakan varian lain.',
        ];
    }
}
