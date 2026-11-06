<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variant = $this->relationLoaded('variant') ? $this->variant : null;

        return [
            'order_detail_id' => $this->order_detail_id,
            'variant_id' => $this->variant_id,
            'sku' => $variant?->sku,
            'size' => $variant?->size,
            'color' => $variant?->color,
            'product_name' => $variant?->product?->product_name,
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
