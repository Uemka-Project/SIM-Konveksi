<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\BomRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BomController extends Controller
{
    public function show(ProductVariant $variant): JsonResponse
    {
        Gate::authorize('bom.view');

        $variant->load(['product', 'materials']);

        return response()->json([
            'data' => [
                'variant' => (new ProductVariantResource($variant))->toArray(request()),
                'items' => $this->items($variant),
            ],
        ]);
    }

    public function update(BomRequest $request, ProductVariant $variant): JsonResponse
    {
        Gate::authorize('bom.update');

        $items = collect($request->validated('items'))
            ->mapWithKeys(fn (array $item): array => [
                (int) $item['material_id'] => ['quantity_per_unit' => $item['quantity_per_unit']],
            ])
            ->all();

        DB::transaction(function () use ($variant, $items): void {
            $variant->materials()->sync($items);
        });

        $variant->load(['product', 'materials']);

        return response()->json([
            'message' => 'BOM varian berhasil disimpan.',
            'data' => [
                'variant' => (new ProductVariantResource($variant))->toArray(request()),
                'items' => $this->items($variant),
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(ProductVariant $variant): array
    {
        return $variant->materials
            ->map(fn ($material): array => [
                'material_id' => $material->material_id,
                'material_name' => $material->material_name,
                'unit_name' => $material->unit_name,
                'current_stock' => (float) $material->current_stock,
                'minimum_stock' => (float) $material->minimum_stock,
                'quantity_per_unit' => (float) $material->pivot->quantity_per_unit,
            ])
            ->values()
            ->all();
    }
}
