<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentStock = (float) $this->current_stock;
        $minimumStock = (float) $this->minimum_stock;

        return [
            'material_id' => $this->material_id,
            'material_name' => $this->material_name,
            'unit_name' => $this->unit_name,
            'current_stock' => $currentStock,
            'minimum_stock' => $minimumStock,
            'is_low_stock' => $currentStock < $minimumStock,
            'variants_count' => $this->whenCounted('variants'),
        ];
    }
}
