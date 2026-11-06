<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\MaterialRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Material::class);

        $query = Material::query()
            ->withCount('variants')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('search')).'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('material_name', 'like', $term)
                        ->orWhere('unit_name', 'like', $term);
                });
            })
            ->when($request->boolean('low_stock'), function ($query): void {
                $query->whereColumn('current_stock', '<', 'minimum_stock');
            })
            ->orderBy('material_name');

        return $this->paginated($query, $request, MaterialResource::class);
    }

    public function store(MaterialRequest $request): JsonResponse
    {
        $this->authorize('create', Material::class);

        // Current stock is only changed through the stock ledger.
        $material = Material::create([
            ...$request->validated(),
            'current_stock' => 0,
        ]);

        return response()->json([
            'message' => 'Bahan berhasil ditambahkan.',
            'data' => new MaterialResource($material),
        ], 201);
    }

    public function show(Material $material): JsonResponse
    {
        $this->authorize('view', $material);

        $material->loadCount('variants');

        return response()->json(['data' => new MaterialResource($material)]);
    }

    public function update(MaterialRequest $request, Material $material): JsonResponse
    {
        $this->authorize('update', $material);

        $material->update($request->validated());

        return response()->json([
            'message' => 'Bahan berhasil diperbarui.',
            'data' => new MaterialResource($material),
        ]);
    }

    public function destroy(Material $material): JsonResponse
    {
        $this->authorize('delete', $material);

        $isUsed = $material->variants()->exists()
            || $material->purchaseDetails()->exists()
            || $material->stockMovements()->exists();

        if ($isUsed) {
            return response()->json([
                'message' => 'Bahan tidak dapat dihapus karena sudah dipakai BOM atau transaksi.',
            ], 422);
        }

        $material->delete();

        return response()->json(['message' => 'Bahan berhasil dihapus.']);
    }
}
