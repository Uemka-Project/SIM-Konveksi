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
use App\Http\Controllers\Api\RoleController;
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
    Route::get('orders/{order}/balance', [OrderController::class, 'balance'])->name('orders.balance');
    Route::get('orders/{order}/payments', [OrderPaymentController::class, 'index'])->name('orders.payments.index');
    Route::post('orders/{order}/payments', [OrderPaymentController::class, 'store'])->name('orders.payments.store');
    Route::delete('payments/{payment}', [OrderPaymentController::class, 'destroy'])->name('payments.destroy');
    Route::apiResource('orders', OrderController::class);

    Route::put('users/{user}/roles', [UserController::class, 'syncRoles'])->name('users.roles.update');
    Route::apiResource('users', UserController::class);

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
});
