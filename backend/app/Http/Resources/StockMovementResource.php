<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'movement_id' => $this->movement_id,
            'material_id' => $this->material_id,
            'material' => $this->whenLoaded('material', fn (): ?array => $this->material ? [
                'material_id' => $this->material->material_id,
                'material_name' => $this->material->material_name,
                'unit_name' => $this->material->unit_name,
                'current_stock' => (float) $this->material->current_stock,
            ] : null),
            'movement_type' => $this->movement_type,
            'quantity' => (float) $this->quantity,
            'movement_date' => $this->movement_date?->format('Y-m-d H:i:s'),
            'purchase_detail_id' => $this->purchase_detail_id,
            'purchase_id' => $this->whenLoaded('purchaseDetail', fn (): ?int => $this->purchaseDetail?->purchase_id),
            'production_order_id' => $this->production_order_id,
            'notes' => $this->notes,
        ];
    }
}
