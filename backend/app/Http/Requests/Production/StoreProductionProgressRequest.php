<?php

namespace App\Http\Requests\Production;

use App\Models\ProductionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $productionOrder = $this->route('productionOrder');

        return $productionOrder instanceof ProductionOrder
            && ($this->user()?->can('recordProgress', $productionOrder) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,employee_id'],
            'stage_code' => [
                'required',
                'string',
                Rule::in(['CUTTING', 'SEWING', 'FINISHING', 'QC']),
            ],
            'status' => ['nullable', 'string', Rule::in(['Proses', 'Selesai'])],
            'started_at' => ['required', 'date'],
            'completed_at' => [
                'nullable',
                'date',
                'required_if:status,Selesai',
                'after_or_equal:started_at',
            ],
            'completed_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Karyawan wajib dipilih.',
            'employee_id.exists' => 'Karyawan yang dipilih tidak ditemukan.',
            'stage_code.required' => 'Tahap produksi wajib dipilih.',
            'stage_code.in' => 'Tahap produksi tidak valid.',
            'status.in' => 'Status progres tidak valid.',
            'started_at.required' => 'Waktu mulai wajib diisi.',
            'started_at.date' => 'Format waktu mulai tidak valid.',
            'completed_at.required_if' => 'Waktu selesai wajib diisi bila progres berstatus Selesai.',
            'completed_at.date' => 'Format waktu selesai tidak valid.',
            'completed_at.after_or_equal' => 'Waktu selesai tidak boleh lebih awal dari waktu mulai.',
            'completed_quantity.integer' => 'Jumlah selesai harus berupa bilangan bulat.',
            'completed_quantity.min' => 'Jumlah selesai tidak boleh negatif.',
        ];
    }
}
