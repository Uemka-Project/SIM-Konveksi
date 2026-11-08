<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Material;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Receive a purchase (FR-S2): write one MASUK movement per item and raise
     * the material stock in the same transaction.
     */
    public function receivePurchase(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase): Purchase {
            $purchase = Purchase::query()
                ->whereKey($purchase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchase->status !== 'Dipesan') {
                $message = "Pembelian berstatus {$purchase->status} tidak dapat diterima.";

                throw new BusinessRuleException($message, ['status' => [$message]]);
            }

            $details = $purchase->details()->lockForUpdate()->get();

            if ($details->isEmpty()) {
                throw new BusinessRuleException(
                    'Pembelian tidak memiliki item sehingga tidak dapat diterima.',
                );
            }

            $receivedAt = now();

            foreach ($details as $detail) {
                StockMovement::create([
                    'material_id' => $detail->material_id,
                    'movement_type' => 'MASUK',
                    'quantity' => (float) $detail->quantity,
                    'movement_date' => $receivedAt,
                    'purchase_detail_id' => $detail->purchase_detail_id,
                    'production_order_id' => null,
                    'notes' => 'Penerimaan pembelian #'.$purchase->purchase_id,
                ]);

                Material::query()
                    ->whereKey($detail->material_id)
                    ->increment('current_stock', (float) $detail->quantity);
            }

            $purchase->update(['status' => 'Diterima']);

            AuditLogger::record('purchase.received', 'Pembelian #'.$purchase->purchase_id
                .' diterima; stok bahan diperbarui.', [
                    'purchase_id' => $purchase->purchase_id,
                    'item_count' => $details->count(),
                ]);

            return $purchase;
        });
    }
}
