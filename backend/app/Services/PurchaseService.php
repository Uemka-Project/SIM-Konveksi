<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    /**
     * Create a purchase and its items. Total and subtotals are calculated
     * here, never trusted from the client (FR-S1).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Purchase
    {
        return DB::transaction(function () use ($attributes): Purchase {
            $details = $attributes['details'];

            $purchase = Purchase::create([
                'supplier_id' => $attributes['supplier_id'],
                'purchase_date' => $attributes['purchase_date'],
                'status' => 'Dipesan',
                'total_amount' => $this->totalFor($details),
            ]);

            $this->createDetails($purchase, $details);

            return $purchase;
        });
    }

    /**
     * Update a purchase that has not been received or cancelled yet.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Purchase $purchase, array $attributes): Purchase
    {
        return DB::transaction(function () use ($purchase, $attributes): Purchase {
            $purchase = $this->lockPurchase($purchase);

            $this->assertPending($purchase, 'diubah');

            $details = $attributes['details'];

            $purchase->update([
                'supplier_id' => $attributes['supplier_id'],
                'purchase_date' => $attributes['purchase_date'],
                'total_amount' => $this->totalFor($details),
            ]);

            $purchase->details()->delete();
            $this->createDetails($purchase, $details);

            return $purchase;
        });
    }

    /**
     * Delete a purchase only while it is still pending.
     */
    public function delete(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase): void {
            $purchase = $this->lockPurchase($purchase);

            $this->assertPending($purchase, 'dihapus');

            if ($purchase->details()->whereHas('stockMovements')->exists()) {
                throw new BusinessRuleException(
                    'Pembelian tidak dapat dihapus karena sudah memiliki mutasi stok.',
                );
            }

            $purchase->details()->delete();
            $purchase->delete();
        });
    }

    /**
     * Cancel a purchase that has not been received yet (FR-S1).
     */
    public function cancel(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase): Purchase {
            $purchase = $this->lockPurchase($purchase);

            $this->assertPending($purchase, 'dibatalkan');

            $purchase->update(['status' => 'Dibatalkan']);

            return $purchase;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $details
     */
    private function totalFor(array $details): float
    {
        return array_reduce(
            $details,
            fn (float $total, array $detail): float => $total
                + $this->subtotal((float) $detail['quantity'], (float) $detail['unit_price']),
            0.0,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $details
     */
    private function createDetails(Purchase $purchase, array $details): void
    {
        foreach ($details as $detail) {
            $quantity = round((float) $detail['quantity'], 2);
            $unitPrice = round((float) $detail['unit_price'], 2);

            $purchase->details()->create([
                'material_id' => $detail['material_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $this->subtotal($quantity, $unitPrice),
            ]);
        }
    }

    private function subtotal(float $quantity, float $unitPrice): float
    {
        return round($quantity * $unitPrice, 2);
    }

    private function assertPending(Purchase $purchase, string $verb): void
    {
        if ($purchase->status !== 'Dipesan') {
            $message = "Pembelian berstatus {$purchase->status} tidak dapat {$verb}.";

            throw new BusinessRuleException($message, ['status' => [$message]]);
        }
    }

    private function lockPurchase(Purchase $purchase): Purchase
    {
        return Purchase::query()
            ->whereKey($purchase->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
