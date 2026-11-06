<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $query = Supplier::query()
            ->withCount('purchases')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('search')).'%';

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('name');

        return $this->paginated($query, $request, SupplierResource::class);
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = Supplier::create($request->validated());

        return response()->json([
            'message' => 'Pemasok berhasil ditambahkan.',
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $this->authorize('view', $supplier);

        $supplier->loadCount('purchases');

        return response()->json(['data' => new SupplierResource($supplier)]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());

        return response()->json([
            'message' => 'Pemasok berhasil diperbarui.',
            'data' => new SupplierResource($supplier),
        ]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->purchases()->exists()) {
            return response()->json([
                'message' => 'Pemasok tidak dapat dihapus karena sudah memiliki pembelian.',
            ], 422);
        }

        $supplier->delete();

        return response()->json(['message' => 'Pemasok berhasil dihapus.']);
    }
}
