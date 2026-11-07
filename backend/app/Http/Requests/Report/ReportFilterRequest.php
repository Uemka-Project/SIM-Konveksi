<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
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
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:20'],
            'material_id' => ['nullable', 'integer', 'exists:materials,material_id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,employee_id'],
            'movement_type' => ['nullable', Rule::in(['MASUK', 'KELUAR'])],
            'stage_code' => ['nullable', Rule::in(['CUTTING', 'SEWING', 'FINISHING', 'QC'])],
            'format' => ['nullable', Rule::in(['csv', 'pdf'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.date' => 'Format tanggal mulai tidak valid.',
            'date_to.date' => 'Format tanggal akhir tidak valid.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.',
            'material_id.exists' => 'Bahan yang dipilih tidak ditemukan.',
            'employee_id.exists' => 'Karyawan yang dipilih tidak ditemukan.',
            'movement_type.in' => 'Tipe mutasi harus MASUK atau KELUAR.',
            'stage_code.in' => 'Tahap produksi tidak valid.',
            'format.in' => 'Format ekspor harus csv atau pdf.',
        ];
    }
}
