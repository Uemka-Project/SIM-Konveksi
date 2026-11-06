<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\PaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderPaymentController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Order $order): JsonResponse
    {
        $this->authorize('view', $order);
        $this->authorize('viewAny', Payment::class);

        $payments = $order->payments()
            ->orderBy('payment_date')
            ->orderBy('payment_id')
            ->get();

        return response()->json([
            'data' => PaymentResource::collection($payments)->toArray(request()),
        ]);
    }

    public function store(PaymentRequest $request, Order $order): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $payment = $this->orders->recordPayment($order, $request->validated());

        return response()->json([
            'message' => 'Pembayaran berhasil dicatat.',
            'data' => new PaymentResource($payment),
        ], 201);
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $this->authorize('delete', $payment);

        $this->orders->deletePayment($payment);

        return response()->json(['message' => 'Pembayaran berhasil dihapus.']);
    }
}
