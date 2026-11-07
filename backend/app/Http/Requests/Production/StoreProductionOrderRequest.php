<?php

namespace App\Http\Requests\Production;

use App\Models\ProductionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductionOrder::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'planned_quantity' => ['required', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date'],
            'target_date' => ['required', 'date'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $startDate = $this->input('start_date');
                $targetDate = $this->input('target_date');

                if ($startDate !== null && $targetDate !== null && $targetDate < $startDate) {
                    $validator->errors()->add(
                        'target_date',
                        'Tanggal target tidak boleh lebih awal dari tanggal mulai.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'planned_quantity.required' => 'Jumlah rencana produksi wajib diisi.',
            'planned_quantity.integer' => 'Jumlah rencana produksi harus berupa bilangan bulat.',
            'planned_quantity.min' => 'Jumlah rencana produksi minimal 1.',
            'start_date.date' => 'Format tanggal mulai tidak valid.',
            'target_date.required' => 'Tanggal target wajib diisi.',
            'target_date.date' => 'Format tanggal target tidak valid.',
        ];
    }
}
