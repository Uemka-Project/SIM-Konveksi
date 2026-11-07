<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Create an order and its items. Subtotal and total are calculated here,
     * never trusted from the client (FR-O1).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Order
    {
        return DB::transaction(function () use ($attributes): Order {
            $details = $attributes['details'];

            $order = Order::create([
                'customer_id' => $attributes['customer_id'],
                'order_date' => $attributes['order_date'],
                'due_date' => $attributes['due_date'],
                'status' => 'Baru',
                'total_amount' => $this->totalFor($details),
            ]);

            $this->createDetails($order, $details);

            return $order;
        });
    }

    /**
     * Update an order that is still in "Baru" status.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Order $order, array $attributes): Order
    {
        return DB::transaction(function () use ($order, $attributes): Order {
            $order = $this->lockOrder($order);

            $this->assertEditable($order);

            $details = $attributes['details'];
            $total = $this->totalFor($details);
            $paid = $this->paidAmount($order);

            if ($total + 0.001 < $paid) {
                throw new BusinessRuleException(
                    'Total pesanan tidak boleh lebih kecil dari pembayaran yang sudah tercatat.',
                    ['details' => [
                        'Total baru Rp'.number_format($total, 0, ',', '.')
                        .' lebih kecil dari pembayaran Rp'.number_format($paid, 0, ',', '.').'.',
                    ]],
                );
            }

            $order->update([
                'customer_id' => $attributes['customer_id'],
                'order_date' => $attributes['order_date'],
                'due_date' => $attributes['due_date'],
                'total_amount' => $total,
            ]);

            $order->details()->delete();
            $this->createDetails($order, $details);

            return $order;
        });
    }

    /**
     * Delete an order only when it is still new and has no transactions.
     */
    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = $this->lockOrder($order);

            $this->assertEditable($order);

            if ($order->payments()->exists()) {
                throw new BusinessRuleException(
                    'Pesanan tidak dapat dihapus karena sudah memiliki pembayaran.',
                );
            }

            if ($order->productionOrders()->exists()) {
                throw new BusinessRuleException(
                    'Pesanan tidak dapat dihapus karena sudah memiliki perintah produksi.',
                );
            }

            $order->details()->delete();
            $order->delete();
        });
    }

    /**
     * Cancel an order (FR-O2). Cancellation is only allowed before any
     * production order exists and while the order is not final yet.
     */
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $order = $this->lockOrder($order);

            if (! in_array($order->status, ['Baru', 'Diproses'], true)) {
                $message = "Pesanan berstatus {$order->status} tidak dapat dibatalkan.";

                throw new BusinessRuleException($message, ['status' => [$message]]);
            }

            if ($order->productionOrders()->exists()) {
                $message = 'Pesanan tidak dapat dibatalkan karena sudah memiliki perintah produksi.';

                throw new BusinessRuleException($message, ['status' => [$message]]);
            }

            $order->update(['status' => 'Dibatalkan']);

            return $order;
        });
    }

    /**
     * Record a payment, never allowing the order to be overpaid (FR-O3).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordPayment(Order $order, array $attributes): Payment
    {
        return DB::transaction(function () use ($order, $attributes): Payment {
            $order = $this->lockOrder($order);

            if ($order->status === 'Dibatalkan') {
                throw new BusinessRuleException(
                    'Pembayaran tidak dapat dicatat untuk pesanan yang dibatalkan.',
                );
            }

            $hasPayments = $order->payments()->exists();

            if (! $hasPayments && ($attributes['payment_type'] ?? null) !== 'DP') {
                $message = 'Pembayaran pertama harus berjenis DP.';

                throw new BusinessRuleException($message, ['payment_type' => [$message]]);
            }

            $remaining = max(0, (float) $order->total_amount - $this->paidAmount($order));
            $amount = round((float) $attributes['amount'], 2);

            if ($amount > $remaining + 0.001) {
                $message = 'Jumlah pembayaran melebihi sisa tagihan '
                    .'Rp'.number_format($remaining, 0, ',', '.').'.';

                throw new BusinessRuleException($message, ['amount' => [$message]]);
            }

            $payment = $order->payments()->create([
                'payment_date' => $attributes['payment_date'],
                'amount' => $amount,
                'payment_method' => $attributes['payment_method'],
                'payment_type' => $attributes['payment_type'],
                'notes' => $attributes['notes'] ?? null,
            ]);

            // FR-O2: the first DP moves a new order into "Diproses".
            if (! $hasPayments && $order->status === 'Baru') {
                $order->update(['status' => 'Diproses']);
            }

            return $payment;
        });
    }

    /**
     * Remove a payment and let the remaining balance recalculate.
     */
    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $this->lockOrder($payment->order);
            $payment->delete();
        });
    }

    /**
     * FR-O4 summary of the amount billed, paid, and still due.
     *
     * @return array<string, mixed>
     */
    public function balance(Order $order): array
    {
        $total = (float) $order->total_amount;
        $paid = $this->paidAmount($order);

        return [
            'order_id' => $order->order_id,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'remaining_amount' => max(0, round($total - $paid, 2)),
            'is_paid' => $paid + 0.001 >= $total,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $details
     */
    private function totalFor(array $details): float
    {
        return array_reduce(
            $details,
            fn (float $total, array $detail): float => $total
                + $this->subtotal((int) $detail['quantity'], (float) $detail['unit_price']),
            0.0,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $details
     */
    private function createDetails(Order $order, array $details): void
    {
        foreach ($details as $detail) {
            $quantity = (int) $detail['quantity'];
            $unitPrice = round((float) $detail['unit_price'], 2);

            $order->details()->create([
                'variant_id' => $detail['variant_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $this->subtotal($quantity, $unitPrice),
            ]);
        }
    }

    private function subtotal(int $quantity, float $unitPrice): float
    {
        return round($quantity * $unitPrice, 2);
    }

    private function assertEditable(Order $order): void
    {
        if ($order->status !== 'Baru') {
            throw new BusinessRuleException(
                'Hanya pesanan berstatus Baru yang dapat diubah atau dihapus.',
                ['status' => ['Pesanan berstatus '.$order->status.' tidak dapat diubah atau dihapus.']],
            );
        }
    }

    private function paidAmount(Order $order): float
    {
        if ($order->relationLoaded('payments')) {
            return round((float) $order->payments->sum('amount'), 2);
        }

        return round((float) $order->payments()->sum('amount'), 2);
    }

    private function lockOrder(Order $order): Order
    {
        return Order::query()
            ->whereKey($order->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
