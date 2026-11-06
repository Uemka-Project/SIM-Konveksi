<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        $this->authorize('viewAny', ProductVariant::class);

        $variants = $product->variants()
            ->with('materials')
            ->withCount('orderDetails')
            ->orderBy('size')
            ->orderBy('color')
            ->get();

        return response()->json([
            'data' => ProductVariantResource::collection($variants)->toArray(request()),
        ]);
    }

    public function store(ProductVariantRequest $request, Product $product): JsonResponse
    {
        $this->authorize('create', ProductVariant::class);

        $variant = $product->variants()->create($request->validated());

        return response()->json([
            'message' => 'Varian berhasil ditambahkan.',
            'data' => new ProductVariantResource($variant),
        ], 201);
    }

    public function update(ProductVariantRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $variant);

        abort_unless($variant->product_id === $product->product_id, 404);

        $variant->update($request->validated());

        return response()->json([
            'message' => 'Varian berhasil diperbarui.',
            'data' => new ProductVariantResource($variant),
        ]);
    }

    public function destroy(Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('delete', $variant);

        abort_unless($variant->product_id === $product->product_id, 404);

        if ($variant->orderDetails()->exists()) {
            return response()->json([
                'message' => 'Varian tidak dapat dihapus karena sudah dipakai pesanan.',
            ], 422);
        }

        DB::transaction(function () use ($variant): void {
            $variant->materials()->detach();
            $variant->delete();
        });

        return response()->json(['message' => 'Varian berhasil dihapus.']);
    }
}
