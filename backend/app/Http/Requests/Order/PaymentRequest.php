<?php

namespace App\Http\Requests\Order;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Payment::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => [
                'required',
                'string',
                Rule::in(['Tunai', 'Transfer', 'QRIS']),
            ],
            'payment_type' => [
                'required',
                'string',
                Rule::in(['DP', 'Cicilan', 'Pelunasan']),
            ],
            'notes' => ['nullable', 'string', 'max:250'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
            'payment_date.date' => 'Format tanggal pembayaran tidak valid.',
            'amount.required' => 'Jumlah pembayaran wajib diisi.',
            'amount.numeric' => 'Jumlah pembayaran harus berupa angka.',
            'amount.gt' => 'Jumlah pembayaran harus lebih dari 0.',
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran tidak valid.',
            'payment_type.required' => 'Jenis pembayaran wajib dipilih.',
            'payment_type.in' => 'Jenis pembayaran tidak valid.',
            'notes.max' => 'Catatan pembayaran maksimal 250 karakter.',
        ];
    }
}
