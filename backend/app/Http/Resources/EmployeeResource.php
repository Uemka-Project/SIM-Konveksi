<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->employee_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'position' => $this->position,
            'active_status' => (bool) $this->active_status,
            'production_progress_count' => $this->whenCounted('productionProgress'),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user ? [
                'user_id' => $this->user->user_id,
                'username' => $this->user->username,
                'is_active' => (bool) $this->user->is_active,
            ] : null),
        ];
    }
}
