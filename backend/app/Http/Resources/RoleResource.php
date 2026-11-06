<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'role_id' => $this->role_id,
            'role_name' => $this->role_name,
            'name' => $this->role_name,
            'description' => $this->description,
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
