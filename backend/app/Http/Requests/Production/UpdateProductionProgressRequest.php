<?php

namespace App\Http\Requests\Production;

use App\Models\ProductionProgress;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductionProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $progress = $this->route('progress');

        return $progress instanceof ProductionProgress
            && ($this->user()?->can('update', $progress) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'required', 'integer', 'exists:employees,employee_id'],
            'stage_code' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['CUTTING', 'SEWING', 'FINISHING', 'QC']),
            ],
            'status' => ['sometimes', 'required', 'string', Rule::in(['Proses', 'Selesai'])],
            'started_at' => ['sometimes', 'required', 'date'],
            'completed_at' => ['nullable', 'date'],
            'completed_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Cross-check the incoming values against the stored row, because PATCH
     * may only send a subset of the fields.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var ProductionProgress|null $progress */
                $progress = $this->route('progress');

                if (! $progress instanceof ProductionProgress) {
                    return;
                }

                $status = (string) $this->input('status', $progress->status);
                $startedAt = Carbon::parse($this->input('started_at', $progress->started_at));
                $completedInput = $this->exists('completed_at')
                    ? $this->input('completed_at')
                    : $progress->completed_at;

                if ($status === 'Selesai') {
                    if ($completedInput === null || $completedInput === '') {
                        $validator->errors()->add(
                            'completed_at',
                            'Waktu selesai wajib diisi bila progres berstatus Selesai.',
                        );

                        return;
                    }

                    if (Carbon::parse($completedInput)->lt($startedAt)) {
                        $validator->errors()->add(
                            'completed_at',
                            'Waktu selesai tidak boleh lebih awal dari waktu mulai.',
                        );
                    }
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
            'employee_id.required' => 'Karyawan wajib dipilih.',
            'employee_id.exists' => 'Karyawan yang dipilih tidak ditemukan.',
            'stage_code.required' => 'Tahap produksi wajib dipilih.',
            'stage_code.in' => 'Tahap produksi tidak valid.',
            'status.required' => 'Status progres wajib diisi.',
            'status.in' => 'Status progres tidak valid.',
            'started_at.required' => 'Waktu mulai wajib diisi.',
            'started_at.date' => 'Format waktu mulai tidak valid.',
            'completed_at.date' => 'Format waktu selesai tidak valid.',
            'completed_quantity.integer' => 'Jumlah selesai harus berupa bilangan bulat.',
            'completed_quantity.min' => 'Jumlah selesai tidak boleh negatif.',
        ];
    }
}
