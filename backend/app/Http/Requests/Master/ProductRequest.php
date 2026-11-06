<?php

namespace App\Http\Requests\Master;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
        /** @var Product|null $product */
        $product = $this->route('product');

        return [
            'product_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'product_name')->ignore($product, 'product_id'),
            ],
            'description' => ['nullable', 'string', 'max:250'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_name.required' => 'Nama produk wajib diisi.',
            'product_name.unique' => 'Nama produk sudah digunakan.',
            'description.max' => 'Deskripsi maksimal 250 karakter.',
        ];
    }
}
