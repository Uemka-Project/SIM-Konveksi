<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'purchase_detail_id' => $this->purchase_detail_id,
            'purchase_id' => $this->purchase_id,
            'material_id' => $this->material_id,
            'material' => $this->whenLoaded('material', fn (): ?array => $this->material ? [
                'material_id' => $this->material->material_id,
                'material_name' => $this->material->material_name,
                'unit_name' => $this->material->unit_name,
            ] : null),
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
