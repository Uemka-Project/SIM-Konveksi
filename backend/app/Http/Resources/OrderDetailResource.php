<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class OrderDetailResource extends OrderResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $productionOrders = $this->relationLoaded('productionOrders')
            ? $this->productionOrders
            : collect();

        return array_merge(parent::toArray($request), [
            'details' => OrderItemResource::collection($this->whenLoaded('details')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'all_production_completed' => $productionOrders->isNotEmpty()
                && $productionOrders->every(fn ($productionOrder): bool => $productionOrder->status === 'Selesai'),
            'production_orders' => $this->whenLoaded('productionOrders', fn () => $productionOrders
                ->map(fn ($productionOrder): array => [
                    'production_order_id' => $productionOrder->production_order_id,
                    'order_detail_id' => $productionOrder->order_detail_id,
                    'planned_quantity' => (int) $productionOrder->planned_quantity,
                    'status' => $productionOrder->status,
                    'start_date' => $productionOrder->start_date?->format('Y-m-d'),
                    'target_date' => $productionOrder->target_date?->format('Y-m-d'),
                ])
                ->values()
                ->all()),
        ]);
    }
}
