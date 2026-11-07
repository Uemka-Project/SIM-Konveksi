<?php

namespace Tests\Feature\Report;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductionOrder;
use App\Models\ProductionProgress;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_and_purchase_reports_aggregate_the_selected_period(): void
    {
        $customer = Customer::create(['name' => 'Toko Berkah Sandang']);
        $supplier = Supplier::create(['name' => 'UD Sumber Tekstil']);

        $firstOrder = $this->makeOrder($customer, '2026-11-05', '2026-11-20', 'Diproses', 1000000);
        $this->makePayment($firstOrder, 400000);

        $this->makeOrder($customer, '2026-11-08', '2026-11-25', 'Baru', 500000);
        $this->makeOrder($customer, '2026-11-09', '2026-11-30', 'Dibatalkan', 300000);
        $this->makeOrder($customer, '2026-10-01', '2026-10-20', 'Diproses', 900000);

        $this->makePurchase($supplier, '2026-11-03', 'Diterima', 200000);
        $this->makePurchase($supplier, '2026-11-04', 'Dibatalkan', 300000);
        $this->makePurchase($supplier, '2026-11-06', 'Dipesan', 400000);
        $this->makePurchase($supplier, '2026-10-02', 'Diterima', 100000);

        $this->login('owner');

        $this->getJson('/api/v1/reports/sales?date_from=2026-11-01&date_to=2026-11-30')
            ->assertOk()
            ->assertJsonPath('data.type', 'sales')
            ->assertJsonPath('data.summary.order_count', 3)
            ->assertJsonPath('data.summary.total_sales', 1500000)
            ->assertJsonPath('data.summary.paid_amount', 400000)
            ->assertJsonPath('data.summary.remaining_amount', 1100000)
            ->assertJsonPath('data.summary.completed_orders', 0)
            ->assertJsonPath('data.summary.cancelled_orders', 1)
            ->assertJsonCount(3, 'data.rows')
            ->assertJsonPath('data.rows.0.order_id', $firstOrder->order_id)
            ->assertJsonPath('data.rows.0.customer', 'Toko Berkah Sandang')
            ->assertJsonPath('data.rows.0.paid_amount', 400000)
            ->assertJsonPath('data.rows.0.remaining_amount', 600000);

        $this->getJson('/api/v1/reports/sales?date_from=2026-11-01&date_to=2026-11-30&status=Dibatalkan')
            ->assertOk()
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.status', 'Dibatalkan');

        $this->getJson('/api/v1/reports/purchases?date_from=2026-11-01&date_to=2026-11-30')
            ->assertOk()
            ->assertJsonPath('data.type', 'purchases')
            ->assertJsonPath('data.summary.purchase_count', 3)
            ->assertJsonPath('data.summary.total_purchases', 600000)
            ->assertJsonPath('data.summary.received_total', 200000)
            ->assertJsonPath('data.summary.ordered_total', 400000)
            ->assertJsonPath('data.summary.cancelled_purchases', 1)
            ->assertJsonCount(3, 'data.rows');
    }

    public function test_stock_card_report_computes_opening_closing_and_running_balance(): void
    {
        $material = Material::create([
            'material_name' => 'Kain Katun',
            'unit_name' => 'meter',
            'current_stock' => 20,
            'minimum_stock' => 5,
        ]);

        StockMovement::create([
            'material_id' => $material->material_id,
            'movement_type' => 'MASUK',
            'quantity' => 10,
            'movement_date' => '2026-10-01 08:00:00',
            'notes' => 'Stok awal Oktober',
        ]);
        StockMovement::create([
            'material_id' => $material->material_id,
            'movement_type' => 'KELUAR',
            'quantity' => 5,
            'movement_date' => '2026-11-05 09:00:00',
            'notes' => 'Serah bahan produksi',
        ]);
        StockMovement::create([
            'material_id' => $material->material_id,
            'movement_type' => 'MASUK',
            'quantity' => 3,
            'movement_date' => '2026-11-10 10:00:00',
            'notes' => 'Pembelian diterima',
        ]);

        $this->login('gudang');

        $this->getJson(
            '/api/v1/reports/stock-card?material_id='.$material->material_id
            .'&date_from=2026-11-01&date_to=2026-11-30',
        )
            ->assertOk()
            ->assertJsonPath('data.type', 'stock-card')
            ->assertJsonPath('data.summary.material.material_name', 'Kain Katun')
            ->assertJsonPath('data.summary.movement_count', 2)
            ->assertJsonPath('data.summary.total_in', 3)
            ->assertJsonPath('data.summary.total_out', 5)
            ->assertJsonPath('data.summary.opening_stock', 22)
            ->assertJsonPath('data.summary.closing_stock', 20)
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.movement_type', 'KELUAR')
            ->assertJsonPath('data.rows.0.balance', 17)
            ->assertJsonPath('data.rows.1.movement_type', 'MASUK')
            ->assertJsonPath('data.rows.1.balance', 20);

        $this->getJson(
            '/api/v1/reports/stock-card?material_id='.$material->material_id
            .'&movement_type=MASUK',
        )
            ->assertOk()
            ->assertJsonPath('data.summary.movement_count', 2)
            ->assertJsonPath('data.summary.opening_stock', 12)
            ->assertJsonPath('data.summary.closing_stock', 20)
            ->assertJsonPath('data.rows.0.balance', 22)
            ->assertJsonPath('data.rows.1.balance', 20);
    }

    public function test_productivity_report_groups_employee_sessions_per_stage(): void
    {
        [$productionOrder, $employeeA, $employeeB] = $this->makeProductionProgressData();

        $this->makeProgress($productionOrder, $employeeA, 'CUTTING', 'Selesai', '2026-11-01 08:00:00', '2026-11-01 09:00:00', 10);
        $this->makeProgress($productionOrder, $employeeA, 'CUTTING', 'Selesai', '2026-11-02 08:00:00', '2026-11-02 08:30:00', 5);
        $this->makeProgress($productionOrder, $employeeA, 'SEWING', 'Proses', '2026-11-03 08:00:00', null, 0);
        $this->makeProgress($productionOrder, $employeeB, 'CUTTING', 'Selesai', '2026-11-01 10:00:00', '2026-11-01 11:30:00', 8);

        $this->login('produksi');

        $this->getJson('/api/v1/reports/productivity?date_from=2026-11-01&date_to=2026-11-30')
            ->assertOk()
            ->assertJsonPath('data.type', 'productivity')
            ->assertJsonPath('data.summary.employee_count', 2)
            ->assertJsonPath('data.summary.session_count', 4)
            ->assertJsonPath('data.summary.completed_sessions', 3)
            ->assertJsonPath('data.summary.completed_quantity', 23)
            ->assertJsonCount(3, 'data.rows')
            ->assertJsonPath('data.rows.0.employee_name', 'Andi Operator')
            ->assertJsonPath('data.rows.0.stage_code', 'CUTTING')
            ->assertJsonPath('data.rows.0.sessions', 2)
            ->assertJsonPath('data.rows.0.completed_sessions', 2)
            ->assertJsonPath('data.rows.0.completed_quantity', 15)
            ->assertJsonPath('data.rows.0.duration_minutes', 90)
            ->assertJsonPath('data.rows.1.stage_code', 'SEWING')
            ->assertJsonPath('data.rows.1.completed_sessions', 0)
            ->assertJsonPath('data.rows.2.employee_name', 'Budi Finishing')
            ->assertJsonPath('data.rows.2.completed_quantity', 8);

        $this->getJson('/api/v1/reports/productivity?stage_code=SEWING')
            ->assertOk()
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.stage_code', 'SEWING');
    }

    public function test_report_access_follows_the_prd_role_matrix(): void
    {
        $endpoints = [
            'sales' => '/api/v1/reports/sales',
            'purchases' => '/api/v1/reports/purchases',
            'stock-card' => '/api/v1/reports/stock-card',
            'productivity' => '/api/v1/reports/productivity',
        ];

        $this->login('produksi');

        $this->getJson($endpoints['sales'])->assertForbidden();
        $this->getJson($endpoints['purchases'])->assertForbidden();
        $this->getJson($endpoints['stock-card'])->assertForbidden();
        $this->getJson($endpoints['productivity'])->assertOk();

        $this->login('gudang');

        $this->getJson($endpoints['sales'])->assertForbidden();
        $this->getJson($endpoints['purchases'])->assertForbidden();
        $this->getJson($endpoints['stock-card'])->assertOk();
        $this->getJson($endpoints['productivity'])->assertForbidden();

        foreach (['admin', 'owner'] as $role) {
            $this->login($role);

            foreach ($endpoints as $endpoint) {
                $this->getJson($endpoint)->assertOk();
            }
        }
    }

    public function test_reports_can_be_exported_as_csv_and_pdf(): void
    {
        $customer = Customer::create(['name' => 'Toko Berkah Sandang']);
        $this->makeOrder($customer, '2026-11-05', '2026-11-20', 'Diproses', 1000000);

        $this->login('admin');

        $csv = $this->get('/api/v1/reports/sales/export?format=csv&date_from=2026-11-01&date_to=2026-11-30')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('Pelanggan', (string) $csv->getContent());
        $this->assertStringContainsString('Toko Berkah Sandang', (string) $csv->getContent());

        $pdf = $this->get('/api/v1/reports/sales/export?format=pdf&date_from=2026-11-01&date_to=2026-11-30')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $content = (string) $pdf->getContent();

        $this->assertStringStartsWith('%PDF-1.4', $content);
        $this->assertStringContainsString('Laporan Penjualan', $content);
        $this->assertStringContainsString('%%EOF', $content);
    }

    public function test_report_filters_are_validated(): void
    {
        $this->login('admin');

        $this->getJson('/api/v1/reports/sales?date_from=2026-11-30&date_to=2026-11-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);

        $this->getJson('/api/v1/reports/stock-card?movement_type=MASUKX')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['movement_type']);

        $this->getJson('/api/v1/reports/stock-card?material_id=999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['material_id']);

        $this->getJson('/api/v1/reports/sales/export?format=xlsx')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['format']);

        $this->getJson('/api/v1/reports/unknown/export')
            ->assertNotFound();
    }

    private function makeOrder(
        Customer $customer,
        string $orderDate,
        string $dueDate,
        string $status,
        float $totalAmount,
    ): Order {
        return Order::create([
            'customer_id' => $customer->customer_id,
            'order_date' => $orderDate,
            'due_date' => $dueDate,
            'status' => $status,
            'total_amount' => $totalAmount,
        ]);
    }

    private function makePayment(Order $order, float $amount): Payment
    {
        return Payment::create([
            'order_id' => $order->order_id,
            'payment_date' => $order->order_date?->format('Y-m-d') ?? '2026-11-05',
            'amount' => $amount,
            'payment_method' => 'Tunai',
            'payment_type' => 'DP',
        ]);
    }

    private function makePurchase(
        Supplier $supplier,
        string $purchaseDate,
        string $status,
        float $totalAmount,
    ): Purchase {
        return Purchase::create([
            'supplier_id' => $supplier->supplier_id,
            'purchase_date' => $purchaseDate,
            'status' => $status,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * @return array{0: ProductionOrder, 1: Employee, 2: Employee}
     */
    private function makeProductionProgressData(): array
    {
        $customer = Customer::create(['name' => 'Toko Berkah Sandang']);
        $product = Product::create(['product_name' => 'Kaos Polos']);
        $variant = ProductVariant::create([
            'product_id' => $product->product_id,
            'size' => 'L',
            'color' => 'Hitam',
            'sku' => 'KO-L-HTM',
        ]);
        $order = $this->makeOrder($customer, '2026-11-01', '2026-11-30', 'Diproses', 1000000);
        $detail = $order->details()->create([
            'variant_id' => $variant->variant_id,
            'quantity' => 10,
            'unit_price' => 100000,
            'subtotal' => 1000000,
        ]);
        $productionOrder = ProductionOrder::create([
            'order_detail_id' => $detail->order_detail_id,
            'planned_quantity' => 10,
            'status' => 'Berjalan',
            'target_date' => '2026-11-30',
        ]);

        $employeeA = Employee::create([
            'name' => 'Andi Operator',
            'position' => 'Operator Cutting',
            'active_status' => true,
        ]);
        $employeeB = Employee::create([
            'name' => 'Budi Finishing',
            'position' => 'Operator Finishing',
            'active_status' => true,
        ]);

        return [$productionOrder, $employeeA, $employeeB];
    }

    private function makeProgress(
        ProductionOrder $productionOrder,
        Employee $employee,
        string $stage,
        string $status,
        string $startedAt,
        ?string $completedAt,
        int $quantity,
    ): ProductionProgress {
        return ProductionProgress::create([
            'production_order_id' => $productionOrder->production_order_id,
            'employee_id' => $employee->employee_id,
            'stage_code' => $stage,
            'status' => $status,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'completed_quantity' => $quantity,
        ]);
    }

    private function login(string $roleName): User
    {
        $role = Role::firstOrCreate(
            ['role_name' => $roleName],
            ['description' => 'Role untuk pengujian.'],
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
