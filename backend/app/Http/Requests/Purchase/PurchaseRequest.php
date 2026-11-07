<?php

namespace App\Http\Requests\Purchase;

use App\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->isMethod('post') ? 'create' : 'update';

        return $this->user()?->can($ability, Purchase::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,supplier_id'],
            'purchase_date' => ['required', 'date'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.material_id' => [
                'required',
                'integer',
                'exists:materials,material_id',
                'distinct',
            ],
            'details.*.quantity' => ['required', 'numeric', 'gt:0'],
            'details.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Pemasok wajib dipilih.',
            'supplier_id.exists' => 'Pemasok tidak ditemukan.',
            'purchase_date.required' => 'Tanggal pembelian wajib diisi.',
            'purchase_date.date' => 'Format tanggal pembelian tidak valid.',
            'details.required' => 'Pembelian harus memiliki minimal satu item.',
            'details.min' => 'Pembelian harus memiliki minimal satu item.',
            'details.*.material_id.required' => 'Bahan wajib dipilih.',
            'details.*.material_id.exists' => 'Bahan tidak ditemukan.',
            'details.*.material_id.distinct' => 'Bahan yang sama tidak boleh dipilih dua kali.',
            'details.*.quantity.required' => 'Jumlah bahan wajib diisi.',
            'details.*.quantity.numeric' => 'Jumlah bahan harus berupa angka.',
            'details.*.quantity.gt' => 'Jumlah bahan harus lebih dari 0.',
            'details.*.unit_price.required' => 'Harga satuan wajib diisi.',
            'details.*.unit_price.numeric' => 'Harga satuan harus berupa angka.',
            'details.*.unit_price.min' => 'Harga satuan tidak boleh negatif.',
        ];
    }
}
