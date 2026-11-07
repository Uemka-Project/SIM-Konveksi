<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\PurchaseService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly StockService $stock,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $query = Purchase::query()
            ->with('supplier')
            ->withCount('details')
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->query('status'));
            })
            ->when($request->filled('date_from'), function ($query) use ($request): void {
                $query->whereDate('purchase_date', '>=', $request->query('date_from'));
            })
            ->when($request->filled('date_to'), function ($query) use ($request): void {
                $query->whereDate('purchase_date', '<=', $request->query('date_to'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->query('search')).'%';

                $query->whereHas('supplier', function ($query) use ($term): void {
                    $query->where('name', 'like', $term);
                });
            })
            ->orderByDesc('purchase_date')
            ->orderByDesc('purchase_id');

        return $this->paginated($query, $request, PurchaseResource::class);
    }

    public function store(PurchaseRequest $request): JsonResponse
    {
        $this->authorize('create', Purchase::class);

        $purchase = $this->purchases->create($request->validated());

        return response()->json([
            'message' => 'Pembelian berhasil dibuat.',
            'data' => new PurchaseResource($this->loadPurchase($purchase)),
        ], 201);
    }

    public function show(Purchase $purchase): JsonResponse
    {
        $this->authorize('view', $purchase);

        return response()->json([
            'data' => new PurchaseResource($this->loadPurchase($purchase)),
        ]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        $purchase = $this->purchases->update($purchase, $request->validated());

        return response()->json([
            'message' => 'Pembelian berhasil diperbarui.',
            'data' => new PurchaseResource($this->loadPurchase($purchase)),
        ]);
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        $this->authorize('delete', $purchase);

        $this->purchases->delete($purchase);

        return response()->json(['message' => 'Pembelian berhasil dihapus.']);
    }

    public function receive(Purchase $purchase): JsonResponse
    {
        $this->authorize('receive', $purchase);

        $purchase = $this->stock->receivePurchase($purchase);

        return response()->json([
            'message' => 'Pembelian diterima. Stok bahan sudah diperbarui.',
            'data' => new PurchaseResource($this->loadPurchase($purchase)),
        ]);
    }

    public function cancel(Purchase $purchase): JsonResponse
    {
        $this->authorize('cancel', $purchase);

        $purchase = $this->purchases->cancel($purchase);

        return response()->json([
            'message' => 'Pembelian berhasil dibatalkan.',
            'data' => new PurchaseResource($this->loadPurchase($purchase)),
        ]);
    }

    private function loadPurchase(Purchase $purchase): Purchase
    {
        return $purchase->load(['supplier', 'details.material']);
    }
}
