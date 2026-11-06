<?php

namespace App\Http\Requests\Order;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethod('POST')) {
            return $this->user()?->can('create', Order::class) ?? false;
        }

        return $this->user()?->can('update', $this->route('order')) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,customer_id'],
            'order_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:order_date'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.variant_id' => [
                'required',
                'integer',
                'exists:product_variants,variant_id',
                'distinct',
            ],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Pelanggan wajib dipilih.',
            'customer_id.exists' => 'Pelanggan yang dipilih tidak ditemukan.',
            'order_date.required' => 'Tanggal pesanan wajib diisi.',
            'order_date.date' => 'Format tanggal pesanan tidak valid.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi.',
            'due_date.date' => 'Format tanggal jatuh tempo tidak valid.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh lebih awal dari tanggal pesanan.',
            'details.required' => 'Pesanan harus memiliki minimal satu item.',
            'details.array' => 'Format item pesanan tidak valid.',
            'details.min' => 'Pesanan harus memiliki minimal satu item.',
            'details.*.variant_id.required' => 'Varian produk wajib dipilih untuk setiap item.',
            'details.*.variant_id.exists' => 'Varian produk yang dipilih tidak ditemukan.',
            'details.*.variant_id.distinct' => 'Varian produk tidak boleh dipilih lebih dari sekali.',
            'details.*.quantity.required' => 'Jumlah setiap item wajib diisi.',
            'details.*.quantity.integer' => 'Jumlah setiap item harus berupa bilangan bulat.',
            'details.*.quantity.min' => 'Jumlah setiap item minimal 1.',
            'details.*.unit_price.required' => 'Harga satuan setiap item wajib diisi.',
            'details.*.unit_price.numeric' => 'Harga satuan setiap item harus berupa angka.',
            'details.*.unit_price.min' => 'Harga satuan tidak boleh negatif.',
        ];
    }
}
