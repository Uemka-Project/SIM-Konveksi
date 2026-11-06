<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialResource;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    /**
     * Read-only summary used by the dashboard screen.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Material::class);

        $lowStock = Material::query()
            ->whereColumn('current_stock', '<', 'minimum_stock')
            ->orderBy('material_name')
            ->limit(10)
            ->get();

        $recentOrders = Order::query()
            ->with('customer')
            ->withSum('payments', 'amount')
            ->orderByDesc('order_date')
            ->orderByDesc('order_id')
            ->limit(5)
            ->get();

        $unpaidTotal = (float) Order::query()
            ->whereNotIn('status', ['Dibatalkan'])
            ->withSum('payments', 'amount')
            ->get()
            ->sum(fn (Order $order): float => max(
                0,
                (float) $order->total_amount - (float) ($order->payments_sum_amount ?? 0),
            ));

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
                'low_stock' => MaterialResource::collection($lowStock)->toArray(request()),
                'recent_orders' => OrderResource::collection($recentOrders)->toArray(request()),
                'unpaid_total' => $unpaidTotal,
            ],
        ]);
    }
}
