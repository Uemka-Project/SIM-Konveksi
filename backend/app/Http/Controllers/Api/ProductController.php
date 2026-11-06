<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::query()
            ->withCount('variants')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('search')).'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('product_name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderBy('product_name');

        return $this->paginated($query, $request, ProductResource::class);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = Product::create($request->validated());

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'data' => new ProductResource($product),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load(['variants' => fn ($query) => $query->withCount('orderDetails')->orderBy('size')])
            ->loadCount('variants');

        return response()->json(['data' => new ProductResource($product)]);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'data' => new ProductResource($product),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        if ($product->variants()->exists()) {
            return response()->json([
                'message' => 'Produk tidak dapat dihapus karena masih memiliki varian.',
            ], 422);
        }

        $product->delete();

        return response()->json(['message' => 'Produk berhasil dihapus.']);
    }
}
