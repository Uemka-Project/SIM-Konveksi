<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Http\Requests\Production\StoreProductionProgressRequest;
use App\Http\Requests\Production\UpdateProductionProgressRequest;
use App\Http\Resources\ProductionOrderResource;
use App\Http\Resources\ProductionProgressResource;
use App\Models\OrderDetail;
use App\Models\ProductionOrder;
use App\Models\ProductionProgress;
use App\Services\ProductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionOrderController extends Controller
{
    public function __construct(private readonly ProductionService $production) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProductionOrder::class);

        $query = ProductionOrder::query()
            ->with([
                'orderDetail.order.customer',
                'orderDetail.variant.product',
            ])
            ->withCount('progress')
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->query('status'));
            })
            ->when($request->filled('order_id'), function ($query) use ($request): void {
                $query->whereHas('orderDetail', function ($query) use ($request): void {
                    $query->where('order_id', $request->query('order_id'));
                });
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $raw = trim((string) $request->query('search'));
                $term = '%'.$raw.'%';

                $query->where(function ($query) use ($term, $raw): void {
                    $query->whereHas('orderDetail.order.customer', function ($query) use ($term): void {
                        $query->where('name', 'like', $term);
                    })
                        ->orWhereHas('orderDetail.variant', function ($query) use ($term): void {
                            $query->where('sku', 'like', $term);
                        })
                        ->orWhereHas('orderDetail.variant.product', function ($query) use ($term): void {
                            $query->where('product_name', 'like', $term);
                        });

                    if (is_numeric($raw)) {
                        $query->orWhere('production_order_id', (int) $raw);
                    }
                });
            })
            ->orderByDesc('production_order_id');

        return $this->paginated($query, $request, ProductionOrderResource::class);
    }

    public function store(
        StoreProductionOrderRequest $request,
        OrderDetail $orderDetail,
    ): JsonResponse {
        $productionOrder = $this->production->createForOrderDetail(
            $orderDetail,
            $request->validated(),
        );

        return response()->json([
            'message' => 'Perintah produksi berhasil dibuat.',
            'data' => new ProductionOrderResource(
                $this->loadProductionOrder($productionOrder),
            ),
        ], 201);
    }

    public function show(ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('view', $productionOrder);

        return response()->json([
            'data' => new ProductionOrderResource(
                $this->loadProductionOrder($productionOrder),
            ),
        ]);
    }

    public function requirements(ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('view', $productionOrder);

        return response()->json([
            'data' => $this->production->requirements($productionOrder),
        ]);
    }

    public function issueMaterials(ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('issueMaterials', $productionOrder);

        $productionOrder = $this->production->issueMaterials($productionOrder);

        return response()->json([
            'message' => 'Bahan berhasil diserahkan. Status produksi menjadi Berjalan.',
            'data' => new ProductionOrderResource(
                $this->loadProductionOrder($productionOrder),
            ),
        ]);
    }

    public function storeProgress(
        StoreProductionProgressRequest $request,
        ProductionOrder $productionOrder,
    ): JsonResponse {
        $progress = $this->production->recordProgress(
            $productionOrder,
            $request->validated(),
        );

        return response()->json([
            'message' => 'Progres produksi berhasil dicatat.',
            'data' => new ProductionProgressResource($progress->load('employee')),
        ], 201);
    }

    public function updateProgress(
        UpdateProductionProgressRequest $request,
        ProductionProgress $progress,
    ): JsonResponse {
        $progress = $this->production->updateProgress(
            $progress,
            $request->validated(),
        );

        return response()->json([
            'message' => 'Progres produksi berhasil diperbarui.',
            'data' => new ProductionProgressResource($progress->load('employee')),
        ]);
    }

    private function loadProductionOrder(ProductionOrder $productionOrder): ProductionOrder
    {
        return $productionOrder->load([
            'orderDetail.order.customer',
            'orderDetail.variant.product',
            'progress' => fn ($query) => $query
                ->with('employee')
                ->orderBy('started_at')
                ->orderBy('progress_id'),
            'stockMovements.material',
        ]);
    }
}
