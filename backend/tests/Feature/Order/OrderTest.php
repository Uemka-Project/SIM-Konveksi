<?php

namespace Tests\Feature\Order;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_order_and_server_calculates_totals(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $customer = Customer::create(['name' => 'Toko Berkah Sandang']);
        $first = $this->variant('KO-M-HTM', 'M', 'Hitam');
        $second = $this->variant('PL-L-NVY', 'L', 'Navy');

        $response = $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->customer_id,
            'order_date' => '2026-12-01',
            'due_date' => '2026-12-10',
            'total_amount' => 1,
            'details' => [
                [
                    'variant_id' => $first->variant_id,
                    'quantity' => 10,
                    'unit_price' => 150000,
                    'subtotal' => 1,
                ],
                [
                    'variant_id' => $second->variant_id,
                    'quantity' => 5,
                    'unit_price' => 120000,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Pesanan berhasil dibuat.')
            ->assertJsonPath('data.status', 'Baru')
            ->assertJsonPath('data.total_amount', 2100000)
            ->assertJsonPath('data.paid_amount', 0)
            ->assertJsonPath('data.remaining_amount', 2100000)
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonCount(2, 'data.details')
            ->assertJsonPath('data.details.0.subtotal', 1500000)
            ->assertJsonPath('data.details.1.subtotal', 600000);

        $orderId = $response->json('data.order_id');

        $this->assertDatabaseHas('orders', [
            'order_id' => $orderId,
            'status' => 'Baru',
            'total_amount' => 2100000,
        ]);
        $this->assertDatabaseCount('order_details', 2);
    }

    public function test_order_validation_returns_422_with_errors(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $customer = Customer::create(['name' => 'Toko Berkah Sandang']);
        $variant = $this->variant('KO-M-HTM', 'M', 'Hitam');

        $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->customer_id,
            'order_date' => '2026-12-10',
            'due_date' => '2026-12-01',
            'details' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['due_date', 'details'])
            ->assertJsonStructure(['message', 'errors' => ['due_date', 'details']]);

        $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->customer_id,
            'order_date' => '2026-12-01',
            'due_date' => '2026-12-10',
            'details' => [
                ['variant_id' => $variant->variant_id, 'quantity' => 0, 'unit_price' => -1],
                ['variant_id' => $variant->variant_id, 'quantity' => 1, 'unit_price' => 1000],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'details.0.quantity',
                'details.0.unit_price',
                'details.1.variant_id',
            ]);
    }

    public function test_admin_can_read_create_and_update_orders_but_cannot_delete_them(): void
    {
        $order = $this->orderWithDetail();
        $customer = Customer::firstOrFail();
        $variant = ProductVariant::firstOrFail();

        Sanctum::actingAs($this->userWithRole('admin'));

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_id', $order->order_id);

        $this->getJson('/api/v1/orders/'.$order->order_id)
            ->assertOk()
            ->assertJsonPath('data.order_id', $order->order_id);

        $created = $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->customer_id,
            'order_date' => '2026-12-02',
            'due_date' => '2026-12-12',
            'details' => [
                ['variant_id' => $variant->variant_id, 'quantity' => 2, 'unit_price' => 50000],
            ],
        ])->assertCreated();

        $createdId = $created->json('data.order_id');

        $this->putJson('/api/v1/orders/'.$order->order_id, [
            'customer_id' => $customer->customer_id,
            'order_date' => '2026-12-01',
            'due_date' => '2026-12-20',
            'details' => [
                ['variant_id' => $variant->variant_id, 'quantity' => 3, 'unit_price' => 50000],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.total_amount', 150000);

        $this->deleteJson('/api/v1/orders/'.$order->order_id)->assertStatus(403);
        $this->deleteJson('/api/v1/orders/'.$createdId)->assertStatus(403);

        $this->assertDatabaseHas('orders', ['order_id' => $order->order_id]);
    }

    public function test_produksi_can_read_orders_but_cannot_create_or_pay(): void
    {
        $order = $this->orderWithDetail();

        Sanctum::actingAs($this->userWithRole('produksi'));

        $this->getJson('/api/v1/orders')->assertOk();
        $this->getJson('/api/v1/orders/'.$order->order_id)->assertOk();

        $this->postJson('/api/v1/orders', [])->assertStatus(403);
        $this->postJson('/api/v1/orders/'.$order->order_id.'/payments', [])
            ->assertStatus(403);
        $this->deleteJson('/api/v1/orders/'.$order->order_id)->assertStatus(403);
    }

    public function test_order_status_transitions_are_enforced(): void
    {
        $order = $this->orderWithDetail();
        Sanctum::actingAs($this->userWithRole('owner'));

        $this->postJson('/api/v1/orders/'.$order->order_id.'/status', ['status' => 'Selesai'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Status pesanan tidak dapat diubah dari Baru ke Selesai.');

        $this->postJson('/api/v1/orders/'.$order->order_id.'/status', ['status' => 'Diproses'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Diproses');

        $this->postJson('/api/v1/orders/'.$order->order_id.'/status', ['status' => 'Selesai'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Selesai');

        $this->postJson('/api/v1/orders/'.$order->order_id.'/status', ['status' => 'Dibatalkan'])
            ->assertStatus(422);

        $this->postJson('/api/v1/orders/'.$order->order_id.'/status', ['status' => 'Baru'])
            ->assertStatus(422);
    }

    public function test_cancelled_order_is_terminal(): void
    {
        $order = $this->orderWithDetail();
        Sanctum::actingAs($this->userWithRole('owner'));

        $this->postJson('/api/v1/orders/'.$order->order_id.'/status', ['status' => 'Dibatalkan'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Dibatalkan');

        $this->postJson('/api/v1/orders/'.$order->order_id.'/status', ['status' => 'Diproses'])
            ->assertStatus(422);
    }

    public function test_order_cannot_be_edited_or_deleted_once_not_new(): void
    {
        $order = $this->orderWithDetail();
        $customer = Customer::firstOrFail();
        $variant = ProductVariant::firstOrFail();

        Sanctum::actingAs($this->userWithRole('owner'));

        $payload = [
            'customer_id' => $customer->customer_id,
            'order_date' => '2026-12-01',
            'due_date' => '2026-12-15',
            'details' => [
                ['variant_id' => $variant->variant_id, 'quantity' => 4, 'unit_price' => 25000],
            ],
        ];

        $this->putJson('/api/v1/orders/'.$order->order_id, $payload)
            ->assertOk()
            ->assertJsonPath('data.total_amount', 100000);

        $this->postJson('/api/v1/orders/'.$order->order_id.'/status', ['status' => 'Diproses'])
            ->assertOk();

        $this->putJson('/api/v1/orders/'.$order->order_id, $payload)->assertStatus(422);
        $this->deleteJson('/api/v1/orders/'.$order->order_id)->assertStatus(422);
    }

    public function test_order_with_payment_cannot_be_deleted(): void
    {
        $order = $this->orderWithDetail();
        Payment::create([
            'order_id' => $order->order_id,
            'payment_date' => '2026-12-01',
            'amount' => 50000,
            'payment_method' => 'Tunai',
            'payment_type' => 'DP',
        ]);

        Sanctum::actingAs($this->userWithRole('owner'));

        $this->deleteJson('/api/v1/orders/'.$order->order_id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pesanan tidak dapat dihapus karena sudah memiliki pembayaran.');

        $this->assertDatabaseHas('orders', ['order_id' => $order->order_id]);
    }

    public function test_owner_can_delete_a_new_order_without_transactions(): void
    {
        $order = $this->orderWithDetail();
        Sanctum::actingAs($this->userWithRole('owner'));

        $this->deleteJson('/api/v1/orders/'.$order->order_id)
            ->assertOk()
            ->assertJsonPath('message', 'Pesanan berhasil dihapus.');

        $this->assertDatabaseMissing('orders', ['order_id' => $order->order_id]);
        $this->assertDatabaseMissing('order_details', ['order_id' => $order->order_id]);
    }

    public function test_payment_cannot_exceed_remaining_and_balance_updates(): void
    {
        $order = $this->orderWithDetail(total: 1000000);
        Sanctum::actingAs($this->userWithRole('owner'));

        $this->postJson('/api/v1/orders/'.$order->order_id.'/payments', [
            'payment_date' => '2026-12-02',
            'amount' => 400000,
            'payment_method' => 'Transfer',
            'payment_type' => 'DP',
            'notes' => 'DP 40 persen',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Pembayaran berhasil dicatat.')
            ->assertJsonPath('data.amount', 400000)
            ->assertJsonPath('data.payment_method', 'Transfer');

        $this->getJson('/api/v1/orders/'.$order->order_id.'/balance')
            ->assertOk()
            ->assertJsonPath('data.total_amount', 1000000)
            ->assertJsonPath('data.paid_amount', 400000)
            ->assertJsonPath('data.remaining_amount', 600000)
            ->assertJsonPath('data.is_paid', false);

        $this->postJson('/api/v1/orders/'.$order->order_id.'/payments', [
            'payment_date' => '2026-12-02',
            'amount' => 700000,
            'payment_method' => 'Tunai',
            'payment_type' => 'Cicilan',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath('message', 'Jumlah pembayaran melebihi sisa tagihan Rp600.000.');

        $this->postJson('/api/v1/orders/'.$order->order_id.'/payments', [
            'payment_date' => '2026-12-03',
            'amount' => 600000,
            'payment_method' => 'QRIS',
            'payment_type' => 'Pelunasan',
        ])->assertCreated();

        $this->getJson('/api/v1/orders/'.$order->order_id.'/balance')
            ->assertOk()
            ->assertJsonPath('data.paid_amount', 1000000)
            ->assertJsonPath('data.remaining_amount', 0)
            ->assertJsonPath('data.is_paid', true);

        $this->postJson('/api/v1/orders/'.$order->order_id.'/payments', [
            'payment_date' => '2026-12-04',
            'amount' => 1,
            'payment_method' => 'Tunai',
            'payment_type' => 'Cicilan',
        ])->assertStatus(422);
    }

    public function test_cancelled_order_cannot_receive_payment(): void
    {
        $order = $this->orderWithDetail(total: 500000);
        $order->update(['status' => 'Dibatalkan']);

        Sanctum::actingAs($this->userWithRole('owner'));

        $this->postJson('/api/v1/orders/'.$order->order_id.'/payments', [
            'payment_date' => '2026-12-02',
            'amount' => 100000,
            'payment_method' => 'Tunai',
            'payment_type' => 'DP',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pembayaran tidak dapat dicatat untuk pesanan yang dibatalkan.');
    }

    public function test_payment_validation_returns_422_with_errors(): void
    {
        $order = $this->orderWithDetail(total: 500000);
        Sanctum::actingAs($this->userWithRole('owner'));

        $this->postJson('/api/v1/orders/'.$order->order_id.'/payments', [
            'payment_date' => '',
            'amount' => 0,
            'payment_method' => 'Kartu Kredit',
            'payment_type' => 'Lainnya',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'payment_date',
                'amount',
                'payment_method',
                'payment_type',
            ]);
    }

    public function test_only_owner_can_delete_payment_and_balance_recalculates(): void
    {
        $order = $this->orderWithDetail(total: 500000);
        $payment = Payment::create([
            'order_id' => $order->order_id,
            'payment_date' => '2026-12-02',
            'amount' => 200000,
            'payment_method' => 'Tunai',
            'payment_type' => 'DP',
        ]);

        Sanctum::actingAs($this->userWithRole('admin'));

        $this->deleteJson('/api/v1/payments/'.$payment->payment_id)
            ->assertStatus(403);

        Sanctum::actingAs($this->userWithRole('owner'));

        $this->deleteJson('/api/v1/payments/'.$payment->payment_id)
            ->assertOk()
            ->assertJsonPath('message', 'Pembayaran berhasil dihapus.');

        $this->getJson('/api/v1/orders/'.$order->order_id.'/balance')
            ->assertOk()
            ->assertJsonPath('data.paid_amount', 0)
            ->assertJsonPath('data.remaining_amount', 500000)
            ->assertJsonPath('data.is_paid', false);
    }

    public function test_admin_can_read_and_record_payments(): void
    {
        $order = $this->orderWithDetail(total: 500000);

        Sanctum::actingAs($this->userWithRole('admin'));

        $this->postJson('/api/v1/orders/'.$order->order_id.'/payments', [
            'payment_date' => '2026-12-02',
            'amount' => 250000,
            'payment_method' => 'Transfer',
            'payment_type' => 'DP',
        ])->assertCreated();

        $this->getJson('/api/v1/orders/'.$order->order_id.'/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 250000);
    }

    public function test_order_index_supports_search_status_filter_and_pagination(): void
    {
        $first = $this->orderWithDetail(customerName: 'Toko Berkah Sandang');
        $second = $this->orderWithDetail(customerName: 'CV Karya Mandiri');
        $second->update(['status' => 'Diproses']);

        Sanctum::actingAs($this->userWithRole('owner'));

        $this->getJson('/api/v1/orders?search=Berkah')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_id', $first->order_id);

        $this->getJson('/api/v1/orders?status=Diproses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_id', $second->order_id);

        $this->getJson('/api/v1/orders?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_variant_options_endpoint_returns_products(): void
    {
        $this->variant('KO-M-HTM', 'M', 'Hitam');

        Sanctum::actingAs($this->userWithRole('owner'));

        $this->getJson('/api/v1/variants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'KO-M-HTM')
            ->assertJsonPath('data.0.product.product_name', 'Kaos Polos');
    }

    private function orderWithDetail(
        float $total = 500000,
        string $customerName = 'Toko Berkah Sandang',
    ): Order {
        $customer = Customer::create(['name' => $customerName]);
        $variant = $this->variant(
            'SKU-'.str_replace(' ', '-', strtoupper($customerName)),
            'L',
            'Abu',
        );

        $order = Order::create([
            'customer_id' => $customer->customer_id,
            'order_date' => '2026-12-01',
            'due_date' => '2026-12-10',
            'status' => 'Baru',
            'total_amount' => $total,
        ]);

        $order->details()->create([
            'variant_id' => $variant->variant_id,
            'quantity' => 1,
            'unit_price' => $total,
            'subtotal' => $total,
        ]);

        return $order;
    }

    private function variant(string $sku, string $size, string $color): ProductVariant
    {
        $product = Product::firstOrCreate(['product_name' => 'Kaos Polos']);

        return ProductVariant::create([
            'product_id' => $product->product_id,
            'size' => $size,
            'color' => $color,
            'sku' => $sku,
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
