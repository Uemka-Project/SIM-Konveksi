<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'description' => $this->description,
            'variants_count' => $this->whenCounted('variants'),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
