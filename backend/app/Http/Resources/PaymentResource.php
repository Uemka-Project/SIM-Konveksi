<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_id' => $this->payment_id,
            'order_id' => $this->order_id,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'payment_type' => $this->payment_type,
            'notes' => $this->notes,
        ];
    }
}
