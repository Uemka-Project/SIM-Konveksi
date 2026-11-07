<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderRequest;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::query()
            ->with('customer')
            ->withCount('details')
            ->withSum('payments', 'amount')
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->query('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $raw = trim((string) $request->query('search'));
                $term = '%'.$raw.'%';

                $query->where(function ($query) use ($term, $raw): void {
                    $query->whereHas('customer', function ($query) use ($term): void {
                        $query->where('name', 'like', $term);
                    });

                    if (is_numeric($raw)) {
                        $query->orWhere('order_id', (int) $raw);
                    }
                });
            })
            ->orderByDesc('order_date')
            ->orderByDesc('order_id');

        return $this->paginated($query, $request, OrderResource::class);
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->orders->create($request->validated());

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'data' => new OrderDetailResource($this->loadOrder($order)),
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json([
            'data' => new OrderDetailResource($this->loadOrder($order)),
        ]);
    }

    public function update(OrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $order = $this->orders->update($order, $request->validated());

        return response()->json([
            'message' => 'Pesanan berhasil diperbarui.',
            'data' => new OrderDetailResource($this->loadOrder($order)),
        ]);
    }

    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);

        $this->orders->delete($order);

        return response()->json(['message' => 'Pesanan berhasil dihapus.']);
    }

    public function cancel(Order $order): JsonResponse
    {
        $this->authorize('update', $order);

        $order = $this->orders->cancel($order);

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
            'data' => new OrderDetailResource($this->loadOrder($order)),
        ]);
    }

    public function balance(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json(['data' => $this->orders->balance($order)]);
    }

    /**
     * Load the relations exposed on the order detail screen. Payment lines are
     * only attached for roles that may read the payments module.
     */
    private function loadOrder(Order $order): Order
    {
        $relations = ['customer', 'details.variant.product', 'productionOrders'];

        if (request()->user()?->can('viewAny', Payment::class)) {
            $relations[] = 'payments';
        }

        return $order->load($relations);
    }
}
