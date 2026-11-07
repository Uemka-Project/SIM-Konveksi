<?php

namespace Tests\Feature\Purchase;

use App\Models\Customer;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_purchase_and_server_calculates_totals(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $supplier = Supplier::create(['name' => 'CV Sumber Kain']);
        $cotton = $this->material('Kain Katun', 'meter', 10, 5);
        $thread = $this->material('Benang Jahit', 'rol', 2, 3);

        $response = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->supplier_id,
            'purchase_date' => '2026-11-10',
            'status' => 'Diterima',
            'total_amount' => 1,
            'details' => [
                [
                    'material_id' => $cotton->material_id,
                    'quantity' => 20,
                    'unit_price' => 25000,
                    'subtotal' => 1,
                ],
                [
                    'material_id' => $thread->material_id,
                    'quantity' => 5,
                    'unit_price' => 12000,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Pembelian berhasil dibuat.')
            ->assertJsonPath('data.status', 'Dipesan')
            ->assertJsonPath('data.total_amount', 560000)
            ->assertJsonCount(2, 'data.details')
            ->assertJsonPath('data.details.0.subtotal', 500000)
            ->assertJsonPath('data.details.1.subtotal', 60000);

        $purchaseId = $response->json('data.purchase_id');

        $this->assertDatabaseHas('purchases', [
            'purchase_id' => $purchaseId,
            'status' => 'Dipesan',
            'total_amount' => 560000,
        ]);
        $this->assertDatabaseCount('purchase_details', 2);
    }

    public function test_purchase_validation_returns_422_with_errors(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $supplier = Supplier::create(['name' => 'CV Sumber Kain']);
        $material = $this->material('Kain Katun', 'meter');

        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->supplier_id,
            'purchase_date' => 'bukan-tanggal',
            'details' => [],
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['purchase_date', 'details']]);

        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->supplier_id,
            'purchase_date' => '2026-11-10',
            'details' => [
                ['material_id' => $material->material_id, 'quantity' => 0, 'unit_price' => -1],
                ['material_id' => $material->material_id, 'quantity' => 1, 'unit_price' => 1000],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'details.0.quantity',
                'details.0.unit_price',
                'details.1.material_id',
            ]);
    }

    public function test_warehouse_can_read_and_receive_but_not_create_edit_or_cancel(): void
    {
        $purchase = $this->purchaseWithDetail();
        $supplier = Supplier::firstOrFail();
        $material = Material::firstOrFail();

        Sanctum::actingAs($this->userWithRole('gudang'));

        $this->getJson('/api/v1/purchases')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/purchases/'.$purchase->purchase_id)
            ->assertOk()
            ->assertJsonPath('data.purchase_id', $purchase->purchase_id);

        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $supplier->supplier_id,
            'purchase_date' => '2026-11-10',
            'details' => [
                ['material_id' => $material->material_id, 'quantity' => 1, 'unit_price' => 1000],
            ],
        ])->assertStatus(403);

        $this->putJson('/api/v1/purchases/'.$purchase->purchase_id, [
            'supplier_id' => $supplier->supplier_id,
            'purchase_date' => '2026-11-11',
            'details' => [
                ['material_id' => $material->material_id, 'quantity' => 2, 'unit_price' => 1000],
            ],
        ])->assertStatus(403);

        $this->postJson('/api/v1/purchases/'.$purchase->purchase_id.'/cancel')
            ->assertStatus(403);

        $this->postJson('/api/v1/purchases/'.$purchase->purchase_id.'/receive')
            ->assertOk()
            ->assertJsonPath('data.status', 'Diterima');
    }

    public function test_admin_cannot_receive_or_delete_purchase(): void
    {
        $purchase = $this->purchaseWithDetail();

        Sanctum::actingAs($this->userWithRole('admin'));

        $this->postJson('/api/v1/purchases/'.$purchase->purchase_id.'/receive')
            ->assertStatus(403);

        $this->deleteJson('/api/v1/purchases/'.$purchase->purchase_id)
            ->assertStatus(403);

        $this->postJson('/api/v1/purchases/'.$purchase->purchase_id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'Dibatalkan');
    }

    public function test_receive_writes_masuk_movements_and_increases_stock(): void
    {
        $purchase = $this->purchaseWithDetail(quantity: 7.5, unitPrice: 20000);
        $material = Material::firstOrFail();

        $this->assertSame(10.0, (float) $material->current_stock);

        Sanctum::actingAs($this->userWithRole('gudang'));

        $this->postJson('/api/v1/purchases/'.$purchase->purchase_id.'/receive')
            ->assertOk()
            ->assertJsonPath('message', 'Pembelian diterima. Stok bahan sudah diperbarui.')
            ->assertJsonPath('data.status', 'Diterima');

        $this->assertDatabaseHas('purchases', [
            'purchase_id' => $purchase->purchase_id,
            'status' => 'Diterima',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'material_id' => $material->material_id,
            'movement_type' => 'MASUK',
            'quantity' => 7.5,
            'purchase_detail_id' => $purchase->details()->firstOrFail()->purchase_detail_id,
            'production_order_id' => null,
        ]);

        $this->assertSame(17.5, (float) $material->refresh()->current_stock);

        $this->postJson('/api/v1/purchases/'.$purchase->purchase_id.'/receive')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pembelian berstatus Diterima tidak dapat diterima.');

        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(17.5, (float) $material->refresh()->current_stock);
    }

    public function test_cancelled_purchase_cannot_be_received_and_received_purchase_cannot_be_cancelled(): void
    {
        $purchase = $this->purchaseWithDetail();

        Sanctum::actingAs($this->userWithRole('owner'));

        $this->postJson('/api/v1/purchases/'.$purchase->purchase_id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'Dibatalkan');

        $this->postJson('/api/v1/purchases/'.$purchase->purchase_id.'/receive')
            ->assertStatus(422);

        $second = $this->purchaseWithDetail();

        $this->postJson('/api/v1/purchases/'.$second->purchase_id.'/receive')
            ->assertOk();

        $this->postJson('/api/v1/purchases/'.$second->purchase_id.'/cancel')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pembelian berstatus Diterima tidak dapat dibatalkan.');
    }

    public function test_purchase_can_only_be_edited_or_deleted_while_pending(): void
    {
        $purchase = $this->purchaseWithDetail(quantity: 4, unitPrice: 10000);
        $supplier = Supplier::firstOrFail();
        $material = Material::firstOrFail();

        Sanctum::actingAs($this->userWithRole('owner'));

        $this->putJson('/api/v1/purchases/'.$purchase->purchase_id, [
            'supplier_id' => $supplier->supplier_id,
            'purchase_date' => '2026-11-12',
            'details' => [
                ['material_id' => $material->material_id, 'quantity' => 6, 'unit_price' => 15000],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.total_amount', 90000)
            ->assertJsonPath('data.details.0.quantity', 6);

        $this->postJson('/api/v1/purchases/'.$purchase->purchase_id.'/receive')->assertOk();

        $this->putJson('/api/v1/purchases/'.$purchase->purchase_id, [
            'supplier_id' => $supplier->supplier_id,
            'purchase_date' => '2026-11-12',
            'details' => [
                ['material_id' => $material->material_id, 'quantity' => 1, 'unit_price' => 1000],
            ],
        ])->assertStatus(422);

        $this->deleteJson('/api/v1/purchases/'.$purchase->purchase_id)->assertStatus(422);
    }

    public function test_owner_can_delete_pending_purchase(): void
    {
        $purchase = $this->purchaseWithDetail();

        Sanctum::actingAs($this->userWithRole('owner'));

        $this->deleteJson('/api/v1/purchases/'.$purchase->purchase_id)
            ->assertOk()
            ->assertJsonPath('message', 'Pembelian berhasil dihapus.');

        $this->assertDatabaseMissing('purchases', ['purchase_id' => $purchase->purchase_id]);
        $this->assertDatabaseMissing('purchase_details', ['purchase_id' => $purchase->purchase_id]);
    }

    public function test_purchase_index_supports_status_search_and_pagination(): void
    {
        $first = $this->purchaseWithDetail(supplierName: 'CV Sumber Kain');
        $second = $this->purchaseWithDetail(supplierName: 'PT Tekstil Nusantara');
        $second->update(['status' => 'Dibatalkan']);

        Sanctum::actingAs($this->userWithRole('owner'));

        $this->getJson('/api/v1/purchases?search=Tekstil')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.purchase_id', $second->purchase_id);

        $this->getJson('/api/v1/purchases?status=Dipesan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.purchase_id', $first->purchase_id);

        $this->getJson('/api/v1/purchases?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_stock_movements_index_and_material_ledger(): void
    {
        $purchase = $this->purchaseWithDetail(quantity: 5, unitPrice: 10000);
        $material = Material::firstOrFail();
        $other = $this->material('Benang Jahit', 'rol', 0, 1);

        StockMovement::create([
            'material_id' => $other->material_id,
            'movement_type' => 'MASUK',
            'quantity' => 3,
            'movement_date' => '2026-11-01 08:00:00',
            'purchase_detail_id' => null,
            'production_order_id' => null,
            'notes' => 'Saldo awal',
        ]);

        Sanctum::actingAs($this->userWithRole('gudang'));

        $this->postJson('/api/v1/purchases/'.$purchase->purchase_id.'/receive')->assertOk();

        $this->getJson('/api/v1/stock-movements')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.movement_type', 'MASUK')
            ->assertJsonPath('data.0.material.material_id', $material->material_id);

        $this->getJson('/api/v1/stock-movements?material_id='.$other->material_id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.notes', 'Saldo awal');

        $this->getJson('/api/v1/materials/'.$material->material_id.'/ledger')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.purchase_id', $purchase->purchase_id)
            ->assertJsonPath('data.0.material.current_stock', 15);
    }

    public function test_low_stock_endpoint_lists_only_materials_below_minimum(): void
    {
        $this->material('Kain Katun', 'meter', 4, 5);
        $this->material('Benang Jahit', 'rol', 5, 5);
        $this->material('Kancing', 'pcs', 10, 2);

        Sanctum::actingAs($this->userWithRole('admin'));

        $this->getJson('/api/v1/materials/low-stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.material_name', 'Kain Katun')
            ->assertJsonPath('data.0.is_low_stock', true);
    }

    public function test_material_requirements_aggregates_bom_per_order(): void
    {
        $customer = Customer::create(['name' => 'Toko Berkah Sandang']);
        $product = Product::create(['product_name' => 'Kaos Polos']);
        $variant = ProductVariant::create([
            'product_id' => $product->product_id,
            'size' => 'L',
            'color' => 'Hitam',
            'sku' => 'KO-L-HTM',
        ]);

        $cotton = $this->material('Kain Katun', 'meter', 12, 5);
        $thread = $this->material('Benang Jahit', 'rol', 1, 2);

        $variant->materials()->attach($cotton->material_id, ['quantity_per_unit' => 1.5]);
        $variant->materials()->attach($thread->material_id, ['quantity_per_unit' => 0.25]);

        $order = Order::create([
            'customer_id' => $customer->customer_id,
            'order_date' => '2026-11-10',
            'due_date' => '2026-11-20',
            'status' => 'Baru',
            'total_amount' => 1000000,
        ]);

        $order->details()->create([
            'variant_id' => $variant->variant_id,
            'quantity' => 10,
            'unit_price' => 100000,
            'subtotal' => 1000000,
        ]);

        Sanctum::actingAs($this->userWithRole('produksi'));

        $this->getJson('/api/v1/orders/'.$order->order_id.'/material-requirements')
            ->assertOk()
            ->assertJsonPath('data.order_id', $order->order_id)
            ->assertJsonPath('data.is_sufficient', false)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.material_name', 'Benang Jahit')
            ->assertJsonPath('data.items.0.required_quantity', 2.5)
            ->assertJsonPath('data.items.0.current_stock', 1)
            ->assertJsonPath('data.items.0.shortage_quantity', 1.5)
            ->assertJsonPath('data.items.0.is_sufficient', false)
            ->assertJsonPath('data.items.1.material_name', 'Kain Katun')
            ->assertJsonPath('data.items.1.required_quantity', 15)
            ->assertJsonPath('data.items.1.shortage_quantity', 3)
            ->assertJsonPath('data.items.1.is_sufficient', false);
    }

    private function purchaseWithDetail(
        float $quantity = 4,
        float $unitPrice = 10000,
        string $supplierName = 'CV Sumber Kain',
    ): Purchase {
        $supplier = Supplier::firstOrCreate(['name' => $supplierName]);
        $material = Material::firstOrCreate(
            ['material_name' => 'Kain Katun'],
            ['unit_name' => 'meter', 'current_stock' => 10, 'minimum_stock' => 5],
        );

        $purchase = Purchase::create([
            'supplier_id' => $supplier->supplier_id,
            'purchase_date' => '2026-11-10',
            'status' => 'Dipesan',
            'total_amount' => $quantity * $unitPrice,
        ]);

        $purchase->details()->create([
            'material_id' => $material->material_id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
        ]);

        return $purchase;
    }

    private function material(
        string $name,
        string $unit,
        float $currentStock = 0,
        float $minimumStock = 0,
    ): Material {
        return Material::create([
            'material_name' => $name,
            'unit_name' => $unit,
            'current_stock' => $currentStock,
            'minimum_stock' => $minimumStock,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(
            ['role_name' => $roleName],
            ['description' => 'Role untuk pengujian.'],
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
