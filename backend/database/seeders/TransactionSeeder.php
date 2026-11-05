<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\ProductionOrder;
use App\Models\ProductionProgress;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\StockMovement;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    /**
     * Seed transaction data from docs/02_dml_contoh.sql.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $orders = $this->seedOrders();
            $purchases = $this->seedPurchases();
            $productionOrders = $this->seedProduction($orders, $purchases);

            $this->seedStockMovements($purchases, $productionOrders);
            $this->seedPayments($orders);
            $this->syncMaterialStock();
        });
    }

    /**
     * @return array<int, Order>
     */
    private function seedOrders(): array
    {
        $orders = [
            1 => ['customer' => 'Toko Berkah Sandang', 'order_date' => '2026-10-05', 'due_date' => '2026-10-20', 'status' => 'Selesai', 'total_amount' => 3000000],
            2 => ['customer' => 'CV Karya Mandiri', 'order_date' => '2026-10-08', 'due_date' => '2026-10-25', 'status' => 'Selesai', 'total_amount' => 4780000],
            3 => ['customer' => 'Koperasi Tani Makmur', 'order_date' => '2026-10-15', 'due_date' => '2026-11-05', 'status' => 'Diproses', 'total_amount' => 3750000],
            4 => ['customer' => 'Komunitas Pati Gowes', 'order_date' => '2026-10-22', 'due_date' => '2026-11-12', 'status' => 'Diproses', 'total_amount' => 3300000],
            5 => ['customer' => 'SMK Harapan Bangsa', 'order_date' => '2026-10-28', 'due_date' => '2026-11-20', 'status' => 'Baru', 'total_amount' => 3480000],
        ];

        $details = [
            [1, 'KO-M-HTM', 30, 60000, 1800000],
            [1, 'KO-L-HTM', 20, 60000, 1200000],
            [2, 'PL-L-NVY', 40, 95000, 3800000],
            [2, 'PL-XL-NVY', 10, 98000, 980000],
            [3, 'SK-L-ABU', 25, 150000, 3750000],
            [4, 'JK-L-HTM', 15, 220000, 3300000],
            [5, 'KO-L-PTH', 60, 58000, 3480000],
        ];

        $result = [];

        foreach ($orders as $number => $data) {
            $customer = Customer::where('name', $data['customer'])->firstOrFail();

            $order = Order::updateOrCreate(
                [
                    'customer_id' => $customer->customer_id,
                    'order_date' => $data['order_date'],
                    'due_date' => $data['due_date'],
                ],
                [
                    'status' => $data['status'],
                    'total_amount' => $data['total_amount'],
                ],
            );

            $result[$number] = $order;
        }

        foreach ($details as [$orderNumber, $sku, $quantity, $unitPrice, $subtotal]) {
            $order = $result[$orderNumber];
            $variant = ProductVariant::where('sku', $sku)->firstOrFail();

            OrderDetail::updateOrCreate(
                [
                    'order_id' => $order->order_id,
                    'variant_id' => $variant->variant_id,
                ],
                [
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ],
            );
        }

        return $result;
    }

    /**
     * @return array<int, Purchase>
     */
    private function seedPurchases(): array
    {
        $purchases = [
            1 => ['supplier' => 'UD Sumber Tekstil', 'purchase_date' => '2026-10-03', 'status' => 'Diterima', 'total_amount' => 3580000],
            2 => ['supplier' => 'Toko Kancing Makmur', 'purchase_date' => '2026-10-04', 'status' => 'Diterima', 'total_amount' => 650000],
            3 => ['supplier' => 'CV Kain Nusantara', 'purchase_date' => '2026-10-14', 'status' => 'Diterima', 'total_amount' => 4500000],
            4 => ['supplier' => 'UD Aksesoris Jaya', 'purchase_date' => '2026-10-20', 'status' => 'Diterima', 'total_amount' => 450000],
            5 => ['supplier' => 'Toko Benang Sentosa', 'purchase_date' => '2026-10-30', 'status' => 'Dipesan', 'total_amount' => 4300000],
        ];

        $details = [
            [1, 'Kain Cotton Combed 30s', 40, 85000, 3400000],
            [1, 'Benang Jahit', 15, 12000, 180000],
            [2, 'Kancing', 1000, 300, 300000],
            [2, 'Tinta Sablon', 5, 70000, 350000],
            [3, 'Kain Drill', 100, 45000, 4500000],
            [4, 'Resleting', 100, 4500, 450000],
            [5, 'Kain Cotton Combed 30s', 50, 86000, 4300000],
        ];

        $result = [];

        foreach ($purchases as $number => $data) {
            $supplier = Supplier::where('name', $data['supplier'])->firstOrFail();

            $purchase = Purchase::updateOrCreate(
                [
                    'supplier_id' => $supplier->supplier_id,
                    'purchase_date' => $data['purchase_date'],
                ],
                [
                    'status' => $data['status'],
                    'total_amount' => $data['total_amount'],
                ],
            );

            $result[$number] = $purchase;
        }

        foreach ($details as [$purchaseNumber, $materialName, $quantity, $unitPrice, $subtotal]) {
            $purchase = $result[$purchaseNumber];
            $material = Material::where('material_name', $materialName)->firstOrFail();

            PurchaseDetail::updateOrCreate(
                [
                    'purchase_id' => $purchase->purchase_id,
                    'material_id' => $material->material_id,
                ],
                [
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ],
            );
        }

        return $result;
    }

    /**
     * @param  array<int, Order>  $orders
     * @return array<int, ProductionOrder>
     */
    private function seedProduction(array $orders, array $purchases): array
    {
        $productionData = [
            1 => ['order' => 1, 'sku' => 'KO-M-HTM', 'planned_quantity' => 30, 'status' => 'Selesai', 'start_date' => '2026-10-07', 'target_date' => '2026-10-18'],
            2 => ['order' => 1, 'sku' => 'KO-L-HTM', 'planned_quantity' => 20, 'status' => 'Selesai', 'start_date' => '2026-10-07', 'target_date' => '2026-10-18'],
            3 => ['order' => 2, 'sku' => 'PL-L-NVY', 'planned_quantity' => 40, 'status' => 'Selesai', 'start_date' => '2026-10-10', 'target_date' => '2026-10-23'],
            4 => ['order' => 2, 'sku' => 'PL-XL-NVY', 'planned_quantity' => 10, 'status' => 'Selesai', 'start_date' => '2026-10-10', 'target_date' => '2026-10-23'],
            5 => ['order' => 3, 'sku' => 'SK-L-ABU', 'planned_quantity' => 25, 'status' => 'Berjalan', 'start_date' => '2026-10-17', 'target_date' => '2026-11-03'],
            6 => ['order' => 4, 'sku' => 'JK-L-HTM', 'planned_quantity' => 15, 'status' => 'Berjalan', 'start_date' => '2026-10-24', 'target_date' => '2026-11-10'],
        ];

        $result = [];

        foreach ($productionData as $number => $data) {
            $order = $orders[$data['order']];
            $variant = ProductVariant::where('sku', $data['sku'])->firstOrFail();
            $detail = OrderDetail::where('order_id', $order->order_id)
                ->where('variant_id', $variant->variant_id)
                ->firstOrFail();

            $result[$number] = ProductionOrder::updateOrCreate(
                [
                    'order_detail_id' => $detail->order_detail_id,
                    'planned_quantity' => $data['planned_quantity'],
                ],
                [
                    'status' => $data['status'],
                    'start_date' => $data['start_date'],
                    'target_date' => $data['target_date'],
                ],
            );
        }

        $progress = [
            [1, 'Rudi Hartono', 'CUTTING', 'Selesai', '2026-10-07 08:00:00', '2026-10-09 16:00:00', 30],
            [1, 'Siti Aminah', 'SEWING', 'Selesai', '2026-10-10 08:00:00', '2026-10-12 16:00:00', 30],
            [1, 'Agus Prasetyo', 'FINISHING', 'Selesai', '2026-10-13 08:00:00', '2026-10-15 16:00:00', 30],
            [1, 'Agus Prasetyo', 'QC', 'Selesai', '2026-10-16 08:00:00', '2026-10-18 16:00:00', 30],
            [2, 'Rudi Hartono', 'CUTTING', 'Selesai', '2026-10-07 08:00:00', '2026-10-09 16:00:00', 20],
            [2, 'Siti Aminah', 'SEWING', 'Selesai', '2026-10-10 08:00:00', '2026-10-12 16:00:00', 20],
            [2, 'Agus Prasetyo', 'FINISHING', 'Selesai', '2026-10-13 08:00:00', '2026-10-15 16:00:00', 20],
            [2, 'Agus Prasetyo', 'QC', 'Selesai', '2026-10-16 08:00:00', '2026-10-18 16:00:00', 20],
            [3, 'Rudi Hartono', 'CUTTING', 'Selesai', '2026-10-10 08:00:00', '2026-10-12 16:00:00', 40],
            [3, 'Siti Aminah', 'SEWING', 'Selesai', '2026-10-13 08:00:00', '2026-10-15 16:00:00', 40],
            [3, 'Agus Prasetyo', 'FINISHING', 'Selesai', '2026-10-16 08:00:00', '2026-10-18 16:00:00', 40],
            [3, 'Agus Prasetyo', 'QC', 'Selesai', '2026-10-19 08:00:00', '2026-10-21 16:00:00', 40],
            [4, 'Rudi Hartono', 'CUTTING', 'Selesai', '2026-10-10 08:00:00', '2026-10-12 16:00:00', 10],
            [4, 'Siti Aminah', 'SEWING', 'Selesai', '2026-10-13 08:00:00', '2026-10-15 16:00:00', 10],
            [4, 'Agus Prasetyo', 'FINISHING', 'Selesai', '2026-10-16 08:00:00', '2026-10-18 16:00:00', 10],
            [4, 'Agus Prasetyo', 'QC', 'Selesai', '2026-10-19 08:00:00', '2026-10-21 16:00:00', 10],
            [5, 'Rudi Hartono', 'CUTTING', 'Selesai', '2026-10-17 08:00:00', '2026-10-19 16:00:00', 25],
            [5, 'Siti Aminah', 'SEWING', 'Proses', '2026-10-20 08:00:00', null, 12],
            [6, 'Rudi Hartono', 'CUTTING', 'Selesai', '2026-10-24 08:00:00', '2026-10-26 16:00:00', 15],
        ];

        foreach ($progress as [$productionNumber, $employeeName, $stage, $status, $startedAt, $completedAt, $completedQuantity]) {
            $productionOrder = $result[$productionNumber];
            $employee = Employee::where('name', $employeeName)->firstOrFail();

            ProductionProgress::updateOrCreate(
                [
                    'production_order_id' => $productionOrder->production_order_id,
                    'employee_id' => $employee->employee_id,
                    'stage_code' => $stage,
                ],
                [
                    'status' => $status,
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'completed_quantity' => $completedQuantity,
                ],
            );
        }

        return $result;
    }

    /**
     * @param  array<int, Purchase>  $purchases
     * @param  array<int, ProductionOrder>  $productionOrders
     */
    private function seedStockMovements(array $purchases, array $productionOrders): void
    {
        $movements = [
            ['material' => 'Kain Cotton Combed 30s', 'type' => 'MASUK', 'quantity' => 40, 'date' => '2026-10-03 10:00:00', 'purchase' => 1, 'notes' => 'Penerimaan pembelian #1'],
            ['material' => 'Benang Jahit', 'type' => 'MASUK', 'quantity' => 15, 'date' => '2026-10-03 10:00:00', 'purchase' => 1, 'notes' => 'Penerimaan pembelian #1'],
            ['material' => 'Kancing', 'type' => 'MASUK', 'quantity' => 1000, 'date' => '2026-10-04 10:00:00', 'purchase' => 2, 'notes' => 'Penerimaan pembelian #2'],
            ['material' => 'Tinta Sablon', 'type' => 'MASUK', 'quantity' => 5, 'date' => '2026-10-04 10:00:00', 'purchase' => 2, 'notes' => 'Penerimaan pembelian #2'],
            ['material' => 'Kain Drill', 'type' => 'MASUK', 'quantity' => 100, 'date' => '2026-10-14 10:00:00', 'purchase' => 3, 'notes' => 'Penerimaan pembelian #3'],
            ['material' => 'Resleting', 'type' => 'MASUK', 'quantity' => 100, 'date' => '2026-10-20 10:00:00', 'purchase' => 4, 'notes' => 'Penerimaan pembelian #4'],
            ['material' => 'Kain Cotton Combed 30s', 'type' => 'KELUAR', 'quantity' => 7.8, 'date' => '2026-10-07 09:00:00', 'production' => 1, 'notes' => 'Pemakaian produksi #1'],
            ['material' => 'Benang Jahit', 'type' => 'KELUAR', 'quantity' => 0.3, 'date' => '2026-10-08 09:00:00', 'production' => 1, 'notes' => 'Pemakaian produksi #1'],
            ['material' => 'Tinta Sablon', 'type' => 'KELUAR', 'quantity' => 0.6, 'date' => '2026-10-12 09:00:00', 'production' => 1, 'notes' => 'Pemakaian produksi #1'],
            ['material' => 'Kain Cotton Combed 30s', 'type' => 'KELUAR', 'quantity' => 5.8, 'date' => '2026-10-07 09:30:00', 'production' => 2, 'notes' => 'Pemakaian produksi #2'],
            ['material' => 'Benang Jahit', 'type' => 'KELUAR', 'quantity' => 0.2, 'date' => '2026-10-08 09:30:00', 'production' => 2, 'notes' => 'Pemakaian produksi #2'],
            ['material' => 'Tinta Sablon', 'type' => 'KELUAR', 'quantity' => 0.4, 'date' => '2026-10-12 09:30:00', 'production' => 2, 'notes' => 'Pemakaian produksi #2'],
            ['material' => 'Kain Cotton Combed 30s', 'type' => 'KELUAR', 'quantity' => 14.5, 'date' => '2026-10-10 09:00:00', 'production' => 3, 'notes' => 'Pemakaian produksi #3'],
            ['material' => 'Benang Jahit', 'type' => 'KELUAR', 'quantity' => 0.6, 'date' => '2026-10-13 09:00:00', 'production' => 3, 'notes' => 'Pemakaian produksi #3'],
            ['material' => 'Kancing', 'type' => 'KELUAR', 'quantity' => 120, 'date' => '2026-10-13 09:30:00', 'production' => 3, 'notes' => 'Pemakaian produksi #3'],
            ['material' => 'Kain Cotton Combed 30s', 'type' => 'KELUAR', 'quantity' => 3.9, 'date' => '2026-10-10 09:30:00', 'production' => 4, 'notes' => 'Pemakaian produksi #4'],
            ['material' => 'Benang Jahit', 'type' => 'KELUAR', 'quantity' => 0.15, 'date' => '2026-10-13 10:00:00', 'production' => 4, 'notes' => 'Pemakaian produksi #4'],
            ['material' => 'Kancing', 'type' => 'KELUAR', 'quantity' => 30, 'date' => '2026-10-13 10:30:00', 'production' => 4, 'notes' => 'Pemakaian produksi #4'],
            ['material' => 'Kain Drill', 'type' => 'KELUAR', 'quantity' => 46, 'date' => '2026-10-17 09:00:00', 'production' => 5, 'notes' => 'Pemakaian produksi #5'],
            ['material' => 'Benang Jahit', 'type' => 'KELUAR', 'quantity' => 0.5, 'date' => '2026-10-20 09:00:00', 'production' => 5, 'notes' => 'Pemakaian produksi #5'],
            ['material' => 'Kancing', 'type' => 'KELUAR', 'quantity' => 150, 'date' => '2026-10-20 09:30:00', 'production' => 5, 'notes' => 'Pemakaian produksi #5'],
            ['material' => 'Kain Drill', 'type' => 'KELUAR', 'quantity' => 33, 'date' => '2026-10-24 09:00:00', 'production' => 6, 'notes' => 'Pemakaian produksi #6'],
            ['material' => 'Resleting', 'type' => 'KELUAR', 'quantity' => 15, 'date' => '2026-10-27 09:00:00', 'production' => 6, 'notes' => 'Pemakaian produksi #6'],
        ];

        foreach ($movements as $movement) {
            $material = Material::where('material_name', $movement['material'])->firstOrFail();

            $attributes = [
                'material_id' => $material->material_id,
                'movement_type' => $movement['type'],
                'quantity' => $movement['quantity'],
                'movement_date' => Carbon::parse($movement['date']),
                'purchase_detail_id' => null,
                'production_order_id' => null,
                'notes' => $movement['notes'],
            ];

            if (isset($movement['purchase'])) {
                $purchase = $purchases[$movement['purchase']];
                $purchaseDetail = PurchaseDetail::where('purchase_id', $purchase->purchase_id)
                    ->where('material_id', $material->material_id)
                    ->firstOrFail();
                $attributes['purchase_detail_id'] = $purchaseDetail->purchase_detail_id;
            }

            if (isset($movement['production'])) {
                $attributes['production_order_id'] = $productionOrders[$movement['production']]->production_order_id;
            }

            StockMovement::firstOrCreate($attributes);
        }
    }

    /**
     * @param  array<int, Order>  $orders
     */
    private function seedPayments(array $orders): void
    {
        $payments = [
            [1, '2026-10-05', 1500000, 'Transfer', 'DP', 'DP 50 persen'],
            [1, '2026-10-20', 1500000, 'Tunai', 'Pelunasan', 'Lunas saat pengambilan'],
            [2, '2026-10-08', 2000000, 'Transfer', 'DP', 'DP awal'],
            [2, '2026-10-15', 1500000, 'QRIS', 'Cicilan', 'Cicilan kedua'],
            [2, '2026-10-25', 1280000, 'Transfer', 'Pelunasan', 'Lunas'],
            [3, '2026-10-15', 1875000, 'Transfer', 'DP', 'DP 50 persen'],
            [4, '2026-10-22', 1000000, 'Tunai', 'DP', 'DP awal'],
            [5, '2026-10-28', 1000000, 'QRIS', 'DP', 'DP awal'],
        ];

        foreach ($payments as [$orderNumber, $date, $amount, $method, $type, $notes]) {
            Payment::updateOrCreate(
                [
                    'order_id' => $orders[$orderNumber]->order_id,
                    'payment_date' => $date,
                    'amount' => $amount,
                ],
                [
                    'payment_method' => $method,
                    'payment_type' => $type,
                    'notes' => $notes,
                ],
            );
        }
    }

    private function syncMaterialStock(): void
    {
        Material::query()->each(function (Material $material): void {
            $incoming = (float) $material->stockMovements()->where('movement_type', 'MASUK')->sum('quantity');
            $outgoing = (float) $material->stockMovements()->where('movement_type', 'KELUAR')->sum('quantity');

            $material->update(['current_stock' => $incoming - $outgoing]);
        });
    }
}
