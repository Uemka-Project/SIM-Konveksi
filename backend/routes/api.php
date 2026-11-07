<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BomController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderPaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\ProductionOrderController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('employees', EmployeeController::class);
    Route::get('materials/low-stock', [MaterialController::class, 'lowStock'])
        ->name('materials.low-stock');
    Route::get('materials/{material}/ledger', [StockMovementController::class, 'ledger'])
        ->name('materials.ledger');
    Route::apiResource('materials', MaterialController::class);
    Route::apiResource('products', ProductController::class);

    Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])
        ->name('products.variants.index');
    Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])
        ->name('products.variants.store');
    Route::put('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])
        ->name('products.variants.update');
    Route::patch('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])
        ->name('products.variants.patch');
    Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])
        ->name('products.variants.destroy');

    Route::get('variants', [ProductVariantController::class, 'options'])->name('variants.index');
    Route::get('variants/{variant}/bom', [BomController::class, 'show'])->name('bom.show');
    Route::put('variants/{variant}/bom', [BomController::class, 'update'])->name('bom.update');

    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/handover', [OrderController::class, 'handover'])->name('orders.handover');
    Route::get('orders/{order}/balance', [OrderController::class, 'balance'])->name('orders.balance');
    Route::get('orders/{order}/material-requirements', [OrderController::class, 'materialRequirements'])
        ->name('orders.material-requirements');
    Route::get('orders/{order}/payments', [OrderPaymentController::class, 'index'])->name('orders.payments.index');
    Route::post('orders/{order}/payments', [OrderPaymentController::class, 'store'])->name('orders.payments.store');
    Route::delete('payments/{payment}', [OrderPaymentController::class, 'destroy'])->name('payments.destroy');
    Route::apiResource('orders', OrderController::class);

    Route::post('order-items/{orderDetail}/production-orders', [ProductionOrderController::class, 'store'])
        ->name('order-items.production-orders.store');
    Route::get('production-orders', [ProductionOrderController::class, 'index'])
        ->name('production-orders.index');
    Route::get('production-orders/{productionOrder}/requirements', [ProductionOrderController::class, 'requirements'])
        ->name('production-orders.requirements');
    Route::post('production-orders/{productionOrder}/issue-materials', [ProductionOrderController::class, 'issueMaterials'])
        ->name('production-orders.issue-materials');
    Route::post('production-orders/{productionOrder}/progress', [ProductionOrderController::class, 'storeProgress'])
        ->name('production-orders.progress.store');
    Route::get('production-orders/{productionOrder}', [ProductionOrderController::class, 'show'])
        ->name('production-orders.show');
    Route::patch('progress/{progress}', [ProductionOrderController::class, 'updateProgress'])
        ->name('progress.update');

    Route::get('stock-movements', [StockMovementController::class, 'index'])
        ->name('stock-movements.index');

    Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('reports/purchases', [ReportController::class, 'purchases'])->name('reports.purchases');
    Route::get('reports/stock-card', [ReportController::class, 'stockCard'])->name('reports.stock-card');
    Route::get('reports/productivity', [ReportController::class, 'productivity'])
        ->name('reports.productivity');
    Route::get('reports/{report}/export', [ReportController::class, 'export'])
        ->whereIn('report', ['sales', 'purchases', 'stock-card', 'productivity'])
        ->name('reports.export');
    Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])
        ->name('purchases.receive');
    Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])
        ->name('purchases.cancel');
    Route::apiResource('purchases', PurchaseController::class);

    Route::put('users/{user}/roles', [UserController::class, 'syncRoles'])->name('users.roles.update');
    Route::apiResource('users', UserController::class);

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
});
