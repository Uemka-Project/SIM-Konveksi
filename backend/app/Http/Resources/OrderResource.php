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
        $paid = (float) ($this->payments_sum_amount
            ?? ($this->relationLoaded('payments')
                ? $this->payments->sum('amount')
                : $this->payments()->sum('amount')));
        $total = (float) $this->total_amount;

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
            'details_count' => $this->whenCounted('details'),
            'total_amount' => $total,
            'paid_amount' => round($paid, 2),
            'remaining_amount' => max(0, round($total - $paid, 2)),
            'is_paid' => $paid + 0.001 >= $total,
            'is_overdue' => $this->due_date !== null
                && $this->status !== 'Selesai'
                && $this->status !== 'Dibatalkan'
                && $this->due_date->isPast(),
        ];
    }
}
