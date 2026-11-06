<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paid = (float) ($this->payments_sum_amount ?? $this->paid_amount ?? 0);

        return [
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer ? [
                'customer_id' => $this->customer->customer_id,
                'name' => $this->customer->name,
            ] : null),
            'order_date' => $this->order_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => $paid,
            'remaining_amount' => max(0, (float) $this->total_amount - $paid),
            'is_overdue' => $this->due_date !== null
                && $this->status !== 'Selesai'
                && $this->status !== 'Dibatalkan'
                && $this->due_date->isPast(),
        ];
    }
}
