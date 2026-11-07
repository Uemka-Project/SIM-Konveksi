<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'progress_id' => $this->progress_id,
            'production_order_id' => $this->production_order_id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'employee_id' => $this->employee->employee_id,
                'name' => $this->employee->name,
                'position' => $this->employee->position,
            ] : null),
            'stage_code' => $this->stage_code,
            'status' => $this->status,
            'started_at' => $this->started_at?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'completed_quantity' => (int) $this->completed_quantity,
        ];
    }
}
