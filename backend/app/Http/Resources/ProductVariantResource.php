<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'variant_id' => $this->variant_id,
            'product_id' => $this->product_id,
            'size' => $this->size,
            'color' => $this->color,
            'sku' => $this->sku,
            'product' => $this->whenLoaded('product', fn (): array => [
                'product_id' => $this->product->product_id,
                'product_name' => $this->product->product_name,
            ]),
            'order_details_count' => $this->whenCounted('orderDetails'),
            'materials' => $this->whenLoaded('materials', fn () => $this->materials
                ->map(fn ($material): array => [
                    'material_id' => $material->material_id,
                    'material_name' => $material->material_name,
                    'unit_name' => $material->unit_name,
                    'current_stock' => (float) $material->current_stock,
                    'quantity_per_unit' => (float) $material->pivot->quantity_per_unit,
                ])
                ->values()
                ->all()),
        ];
    }
}
