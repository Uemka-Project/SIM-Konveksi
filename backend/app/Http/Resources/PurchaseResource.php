<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'purchase_id' => $this->purchase_id,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn (): ?array => $this->supplier ? [
                'supplier_id' => $this->supplier->supplier_id,
                'name' => $this->supplier->name,
            ] : null),
            'purchase_date' => $this->purchase_date?->format('Y-m-d'),
            'status' => $this->status,
            'total_amount' => (float) $this->total_amount,
            'details_count' => $this->whenCounted('details'),
            'details' => PurchaseDetailResource::collection($this->whenLoaded('details')),
        ];
    }
}
