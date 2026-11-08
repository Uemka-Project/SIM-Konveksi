<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\AuditLogger;
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

            AuditLogger::record('order.created', 'Pesanan #'.$order->order_id.' dibuat.', [
                'order_id' => $order->order_id,
                'customer_id' => $order->customer_id,
                'total_amount' => (float) $order->total_amount,
            ]);

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

            AuditLogger::record('order.updated', 'Pesanan #'.$order->order_id.' diperbarui.', [
                'order_id' => $order->order_id,
                'total_amount' => (float) $order->total_amount,
            ]);

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

            AuditLogger::record('order.deleted', 'Pesanan #'.$order->order_id.' dihapus.', [
                'order_id' => $order->order_id,
            ]);
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

            AuditLogger::record('order.cancelled', 'Pesanan #'.$order->order_id.' dibatalkan.', [
                'order_id' => $order->order_id,
            ]);

            return $order;
        });
    }

    /**
     * FR-O6: hand the finished products over to the customer. This is only
     * allowed when every production order is Selesai and the invoice is paid.
     */
    public function handover(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $order = $this->lockOrder($order);

            if ($order->status !== 'Diproses') {
                $message = $order->status === 'Selesai'
                    ? 'Produk untuk pesanan ini sudah diserahkan.'
                    : "Pesanan berstatus {$order->status} tidak dapat diserahkan.";

                throw new BusinessRuleException($message, ['status' => [$message]]);
            }

            $productionOrders = $order->productionOrders()
                ->lockForUpdate()
                ->get();

            if ($productionOrders->isEmpty()) {
                $message = 'Pesanan belum memiliki perintah produksi sehingga belum dapat diserahkan.';

                throw new BusinessRuleException($message, ['status' => [$message]]);
            }

            if ($productionOrders->contains(fn ($productionOrder): bool => $productionOrder->status !== 'Selesai')) {
                $message = 'Semua perintah produksi pesanan harus berstatus Selesai sebelum produk diserahkan.';

                throw new BusinessRuleException($message, ['status' => [$message]]);
            }

            $remaining = max(0, round((float) $order->total_amount - $this->paidAmount($order), 2));

            if ($remaining > 0.001) {
                $message = 'Sisa tagihan Rp'.number_format($remaining, 0, ',', '.')
                    .' harus dilunasi sebelum produk diserahkan.';

                throw new BusinessRuleException($message, ['payment' => [$message]]);
            }

            $order->update(['status' => 'Selesai']);

            AuditLogger::record('order.handed_over', 'Produk pesanan #'.$order->order_id.' diserahkan.', [
                'order_id' => $order->order_id,
            ]);

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

            AuditLogger::record('payment.recorded', 'Pembayaran #'.$payment->payment_id
                .' untuk pesanan #'.$order->order_id.' dicatat.', [
                    'payment_id' => $payment->payment_id,
                    'order_id' => $order->order_id,
                    'amount' => (float) $payment->amount,
                    'payment_type' => $payment->payment_type,
                ]);

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

            $attributes = [
                'payment_id' => $payment->payment_id,
                'order_id' => $payment->order_id,
                'amount' => (float) $payment->amount,
            ];

            $payment->delete();

            AuditLogger::record('payment.deleted', 'Pembayaran #'.$attributes['payment_id']
                .' pada pesanan #'.$attributes['order_id'].' dihapus.', $attributes);
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
     * FR-P2: aggregate BOM requirements for an order and compare them with
     * the current material stock.
     *
     * @return array<string, mixed>
     */
    public function materialRequirements(Order $order): array
    {
        $requirements = [];

        foreach ($order->details()->with('variant.materials')->get() as $detail) {
            $variant = $detail->variant;

            if ($variant === null) {
                continue;
            }

            $quantity = (float) $detail->quantity;

            foreach ($variant->materials as $material) {
                $materialId = $material->material_id;
                $required = round($quantity * (float) $material->pivot->quantity_per_unit, 2);

                if (! isset($requirements[$materialId])) {
                    $requirements[$materialId] = [
                        'material_id' => $materialId,
                        'material_name' => $material->material_name,
                        'unit_name' => $material->unit_name,
                        'required_quantity' => 0.0,
                        'current_stock' => (float) $material->current_stock,
                        'minimum_stock' => (float) $material->minimum_stock,
                    ];
                }

                $requirements[$materialId]['required_quantity'] = round(
                    $requirements[$materialId]['required_quantity'] + $required,
                    2,
                );
            }
        }

        $items = array_map(function (array $requirement): array {
            $shortage = max(
                0,
                round($requirement['required_quantity'] - $requirement['current_stock'], 2),
            );

            return [
                ...$requirement,
                'shortage_quantity' => $shortage,
                'is_sufficient' => $shortage <= 0,
            ];
        }, array_values($requirements));

        usort(
            $items,
            fn (array $left, array $right): int => strcmp(
                $left['material_name'],
                $right['material_name'],
            ),
        );

        return [
            'order_id' => $order->order_id,
            'items' => $items,
            'is_sufficient' => ! in_array(false, array_column($items, 'is_sufficient'), true),
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
