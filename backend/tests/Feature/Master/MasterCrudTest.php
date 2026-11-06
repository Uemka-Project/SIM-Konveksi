<?php

namespace Tests\Feature\Master;

use App\Models\Customer;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_update_and_delete_a_customer(): void
    {
        $owner = $this->userWithRole('owner');

        Sanctum::actingAs($owner);

        $created = $this->postJson('/api/v1/customers', [
            'name' => 'Toko Maju Jaya',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka 10',
            'email' => 'maju@example.test',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Pelanggan berhasil ditambahkan.')
            ->assertJsonPath('data.name', 'Toko Maju Jaya');

        $customerId = $created->json('data.customer_id');

        $this->putJson('/api/v1/customers/'.$customerId, [
            'name' => 'Toko Maju Jaya Abadi',
            'phone' => '081234567890',
            'address' => 'Jl. Merdeka 10',
            'email' => 'maju@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Toko Maju Jaya Abadi');

        $this->deleteJson('/api/v1/customers/'.$customerId)
            ->assertOk()
            ->assertJsonPath('message', 'Pelanggan berhasil dihapus.');

        $this->assertDatabaseMissing('customers', ['customer_id' => $customerId]);
    }

    public function test_customer_validation_returns_422_with_errors(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $this->postJson('/api/v1/customers', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_produksi_role_can_read_customers_but_not_create_them(): void
    {
        Sanctum::actingAs($this->userWithRole('produksi'));

        Customer::create(['name' => 'Pelanggan Lama']);

        $this->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);

        $this->postJson('/api/v1/customers', ['name' => 'Pelanggan Baru'])
            ->assertStatus(403);
    }

    public function test_gudang_role_can_manage_suppliers(): void
    {
        Sanctum::actingAs($this->userWithRole('gudang'));

        $this->postJson('/api/v1/suppliers', ['name' => 'PT Bahan Tekstil'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'PT Bahan Tekstil');
    }

    public function test_admin_role_can_only_read_suppliers(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->getJson('/api/v1/suppliers')->assertOk();

        $this->postJson('/api/v1/suppliers', ['name' => 'PT Bahan Tekstil'])
            ->assertStatus(403);
    }

    public function test_customer_with_orders_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $customer = Customer::create(['name' => 'Pelanggan Setia']);

        Order::create([
            'customer_id' => $customer->customer_id,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'status' => 'Baru',
            'total_amount' => 150000,
        ]);

        $this->deleteJson('/api/v1/customers/'.$customer->customer_id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pelanggan tidak dapat dihapus karena sudah memiliki pesanan.');

        $this->assertDatabaseHas('customers', ['customer_id' => $customer->customer_id]);
    }

    public function test_material_used_by_bom_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $product = Product::create(['product_name' => 'Kemeja']);
        $variant = ProductVariant::create([
            'product_id' => $product->product_id,
            'size' => 'L',
            'color' => 'Putih',
            'sku' => 'KMJ-L-PTH',
        ]);
        $material = Material::create([
            'material_name' => 'Kain Katun',
            'unit_name' => 'meter',
            'current_stock' => 10,
            'minimum_stock' => 5,
        ]);

        $variant->materials()->attach($material->material_id, ['quantity_per_unit' => 1.5]);

        $this->deleteJson('/api/v1/materials/'.$material->material_id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Bahan tidak dapat dihapus karena sudah dipakai BOM atau transaksi.');
    }

    public function test_product_with_variants_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $product = Product::create(['product_name' => 'Jaket']);
        ProductVariant::create([
            'product_id' => $product->product_id,
            'size' => 'M',
            'color' => 'Hitam',
            'sku' => 'JKT-M-HTM',
        ]);

        $this->deleteJson('/api/v1/products/'.$product->product_id)
            ->assertStatus(422);
    }

    public function test_material_index_supports_search_pagination_and_low_stock_filter(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        Material::create([
            'material_name' => 'Benang Jahit',
            'unit_name' => 'roll',
            'current_stock' => 1,
            'minimum_stock' => 5,
        ]);
        Material::create([
            'material_name' => 'Kain Denim',
            'unit_name' => 'meter',
            'current_stock' => 50,
            'minimum_stock' => 10,
        ]);

        $this->getJson('/api/v1/materials?search=Benang')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.material_name', 'Benang Jahit')
            ->assertJsonPath('data.0.is_low_stock', true);

        $this->getJson('/api/v1/materials?low_stock=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 20);

        $this->getJson('/api/v1/materials?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_gudang_can_update_material_but_not_delete_it(): void
    {
        Sanctum::actingAs($this->userWithRole('gudang'));

        $material = Material::create([
            'material_name' => 'Kain Flanel',
            'unit_name' => 'meter',
            'current_stock' => 4,
            'minimum_stock' => 2,
        ]);

        $this->putJson('/api/v1/materials/'.$material->material_id, [
            'material_name' => 'Kain Flanel Premium',
            'unit_name' => 'meter',
            'minimum_stock' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('data.material_name', 'Kain Flanel Premium');

        $this->deleteJson('/api/v1/materials/'.$material->material_id)
            ->assertStatus(403);
    }

    public function test_employee_without_transactions_can_be_deleted(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $employee = \App\Models\Employee::create([
            'name' => 'Siti',
            'position' => 'Penjahit',
            'active_status' => true,
        ]);

        $this->deleteJson('/api/v1/employees/'.$employee->employee_id)
            ->assertOk();

        $this->assertDatabaseMissing('employees', ['employee_id' => $employee->employee_id]);
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
