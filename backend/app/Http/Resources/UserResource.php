<?php

namespace App\Http\Resources;

use App\Support\AccessMatrix;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'employee_id' => $this->employee_id,
            'username' => $this->username,
            'email' => $this->email,
            'name' => $this->employee?->name ?? $this->username,
            'is_active' => (bool) $this->is_active,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'role_names' => $this->whenLoaded('roles', fn (): array => $this->roles
                ->pluck('role_name')
                ->values()
                ->all()),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'employee_id' => $this->employee->employee_id,
                'name' => $this->employee->name,
                'position' => $this->employee->position,
            ] : null),
            'permissions' => AccessMatrix::permissions($this->resource),
        ];
    }
}
