<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->relationLoaded('orderDetail') ? $this->orderDetail : null;
        $order = $detail !== null && $detail->relationLoaded('order') ? $detail->order : null;
        $variant = $detail !== null && $detail->relationLoaded('variant') ? $detail->variant : null;
        $product = $variant !== null && $variant->relationLoaded('product')
            ? $variant->product
            : null;

        return [
            'production_order_id' => $this->production_order_id,
            'order_detail_id' => $this->order_detail_id,
            'planned_quantity' => (int) $this->planned_quantity,
            'status' => $this->status,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'target_date' => $this->target_date?->format('Y-m-d'),
            'progress_count' => $this->whenCounted('progress'),
            'order' => $this->when($order !== null, fn (): array => [
                'order_id' => $order->order_id,
                'status' => $order->status,
                'order_date' => $order->order_date?->format('Y-m-d'),
                'due_date' => $order->due_date?->format('Y-m-d'),
                'customer' => $order->relationLoaded('customer') && $order->customer
                    ? [
                        'customer_id' => $order->customer->customer_id,
                        'name' => $order->customer->name,
                    ]
                    : null,
            ]),
            'item' => $this->when($variant !== null, fn (): array => [
                'variant_id' => $variant->variant_id,
                'order_quantity' => (int) $detail->quantity,
                'sku' => $variant->sku,
                'size' => $variant->size,
                'color' => $variant->color,
                'product_name' => $product?->product_name,
            ]),
            'progress' => ProductionProgressResource::collection(
                $this->whenLoaded('progress'),
            ),
            'stock_movements' => $this->whenLoaded('stockMovements', fn () => $this->stockMovements
                ->map(fn ($movement): array => [
                    'movement_id' => $movement->movement_id,
                    'material_id' => $movement->material_id,
                    'material' => $movement->relationLoaded('material') && $movement->material
                        ? [
                            'material_id' => $movement->material->material_id,
                            'material_name' => $movement->material->material_name,
                            'unit_name' => $movement->material->unit_name,
                        ]
                        : null,
                    'movement_type' => $movement->movement_type,
                    'quantity' => (float) $movement->quantity,
                    'movement_date' => $movement->movement_date?->format('Y-m-d H:i:s'),
                    'notes' => $movement->notes,
                ])
                ->values()
                ->all()),
        ];
    }
}
