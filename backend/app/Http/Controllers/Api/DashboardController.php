<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductionOrderResource;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    /**
     * Read-only summary used by the dashboard screen (FR-R1).
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Material::class);

        $today = now()->toDateString();
        $dueSoonUntil = now()->addDays(7)->toDateString();
        $activeStatuses = ['Baru', 'Diproses'];

        $lowStock = Material::query()
            ->whereColumn('current_stock', '<', 'minimum_stock')
            ->orderBy('material_name')
            ->limit(10)
            ->get();

        $lowStockCount = Material::query()
            ->whereColumn('current_stock', '<', 'minimum_stock')
            ->count();

        $recentOrders = $this->orderQuery()
            ->orderByDesc('order_date')
            ->orderByDesc('order_id')
            ->limit(5)
            ->get();

        $activeOrders = $this->orderQuery()
            ->whereIn('status', $activeStatuses)
            ->orderBy('due_date')
            ->orderBy('order_id')
            ->limit(10)
            ->get();

        $dueSoonOrders = $this->orderQuery()
            ->whereIn('status', $activeStatuses)
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $dueSoonUntil)
            ->orderBy('due_date')
            ->orderBy('order_id')
            ->limit(10)
            ->get();

        $overdueOrders = $this->orderQuery()
            ->whereIn('status', $activeStatuses)
            ->whereDate('due_date', '<', $today)
            ->orderBy('due_date')
            ->orderBy('order_id')
            ->limit(10)
            ->get();

        $unpaidOrders = $this->orderQuery()
            ->whereNotIn('status', ['Dibatalkan'])
            ->get();

        $unpaidTotal = (float) $unpaidOrders->sum(
            fn (Order $order): float => max(
                0,
                (float) $order->total_amount - (float) ($order->payments_sum_amount ?? 0),
            ),
        );
        $unpaidCount = $unpaidOrders
            ->filter(fn (Order $order): bool => (float) $order->total_amount
                - (float) ($order->payments_sum_amount ?? 0) > 0)
            ->count();

        // FR-P5/FR-O6: every production order is Selesai and the invoice is
        // fully paid, so the admin only has to hand the products over.
        $readyForHandover = $this->orderQuery()
            ->where('status', 'Diproses')
            ->whereHas('productionOrders')
            ->whereDoesntHave('productionOrders', function (Builder $query): void {
                $query->where('status', '!=', 'Selesai');
            })
            ->orderBy('due_date')
            ->orderBy('order_id')
            ->get()
            ->filter(fn (Order $order): bool => (float) $order->total_amount
                - (float) ($order->payments_sum_amount ?? 0) <= 0.001)
            ->values();

        $runningProduction = ProductionOrder::query()
            ->with([
                'orderDetail.order.customer',
                'orderDetail.variant.product',
                'progress' => fn ($query) => $query
                    ->with('employee')
                    ->orderBy('started_at')
                    ->orderBy('progress_id'),
            ])
            ->withCount('progress')
            ->where('status', 'Berjalan')
            ->orderBy('target_date')
            ->orderBy('production_order_id')
            ->limit(10)
            ->get();

        $activeCount = Order::query()->whereIn('status', $activeStatuses)->count();
        $dueSoonCount = Order::query()
            ->whereIn('status', $activeStatuses)
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $dueSoonUntil)
            ->count();
        $overdueCount = Order::query()
            ->whereIn('status', $activeStatuses)
            ->whereDate('due_date', '<', $today)
            ->count();
        $runningCount = ProductionOrder::query()->where('status', 'Berjalan')->count();

        return response()->json([
            'data' => [
                'counts' => [
                    'customers' => Customer::count(),
                    'suppliers' => Supplier::count(),
                    'employees' => Employee::count(),
                    'materials' => Material::count(),
                    'products' => Product::count(),
                    'orders' => Order::count(),
                ],
                'summary' => [
                    'active_orders' => $activeCount,
                    'due_soon_orders' => $dueSoonCount,
                    'overdue_orders' => $overdueCount,
                    'running_production' => $runningCount,
                    'low_stock_materials' => $lowStockCount,
                    'unpaid_orders' => $unpaidCount,
                    'unpaid_total' => round($unpaidTotal, 2),
                    'ready_for_handover' => $readyForHandover->count(),
                ],
                'low_stock' => MaterialResource::collection($lowStock)->toArray(request()),
                'recent_orders' => OrderResource::collection($recentOrders)->toArray(request()),
                'active_orders' => OrderResource::collection($activeOrders)->toArray(request()),
                'due_soon_orders' => OrderResource::collection($dueSoonOrders)->toArray(request()),
                'overdue_orders' => OrderResource::collection($overdueOrders)->toArray(request()),
                'running_production' => ProductionOrderResource::collection($runningProduction)
                    ->toArray(request()),
                'ready_for_handover' => OrderResource::collection(
                    $readyForHandover->take(10)->values(),
                )->toArray(request()),
                'unpaid_total' => round($unpaidTotal, 2),
            ],
        ]);
    }

    private function orderQuery(): Builder
    {
        return Order::query()
            ->with('customer')
            ->withSum('payments', 'amount');
    }
}
