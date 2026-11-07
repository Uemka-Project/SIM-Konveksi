<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductionOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_production_orders_and_batches_cannot_exceed_item_quantity(): void
    {
        [$order, $detail] = $this->makeOrderWithBom(quantity: 10);

        $this->login('admin');

        $first = $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
            'planned_quantity' => 4,
            'start_date' => '2026-11-10',
            'target_date' => '2026-11-20',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Perintah produksi berhasil dibuat.')
            ->assertJsonPath('data.status', 'Menunggu')
            ->assertJsonPath('data.planned_quantity', 4)
            ->assertJsonPath('data.order.order_id', $order->order_id)
            ->assertJsonPath('data.item.sku', 'KO-L-HTM')
            ->assertJsonPath('data.item.order_quantity', 10)
            ->assertJsonPath('data.start_date', '2026-11-10')
            ->assertJsonPath('data.target_date', '2026-11-20');

        $firstId = $first->json('data.production_order_id');

        $this->assertDatabaseHas('production_orders', [
            'production_order_id' => $firstId,
            'order_detail_id' => $detail->order_detail_id,
            'planned_quantity' => 4,
            'status' => 'Menunggu',
        ]);

        $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
            'planned_quantity' => 4,
            'target_date' => '2026-11-22',
        ])->assertCreated();

        $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
            'planned_quantity' => 3,
            'target_date' => '2026-11-25',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Jumlah rencana produksi melebihi sisa item pesanan (2).');

        $this->assertDatabaseCount('production_orders', 2);
        $this->assertSame(
            8,
            (int) ProductionOrder::where('order_detail_id', $detail->order_detail_id)->sum('planned_quantity'),
        );
    }

    public function test_spk_requires_an_order_with_dp_and_diproses_status(): void
    {
        [$order, $detail] = $this->makeOrderWithBom(
            quantity: 10,
            status: 'Baru',
            withDp: false,
        );

        $this->login('admin');

        $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
            'planned_quantity' => 2,
            'target_date' => '2026-11-20',
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Perintah produksi hanya dapat dibuat setelah pesanan memiliki DP dan berstatus Diproses.',
            );

        $order->update(['status' => 'Diproses']);

        $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
            'planned_quantity' => 2,
            'target_date' => '2026-11-20',
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Perintah produksi hanya dapat dibuat setelah pembayaran DP dicatat.',
            );

        $this->makePayment($order, 100000, 'DP');

        $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
            'planned_quantity' => 2,
            'target_date' => '2026-11-20',
        ])->assertCreated();
    }

    public function test_spk_validation_rejects_invalid_quantity_and_dates(): void
    {
        [, $detail] = $this->makeOrderWithBom(quantity: 10);

        $this->login('admin');

        $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
            'planned_quantity' => 0,
            'start_date' => '2026-11-20',
            'target_date' => '2026-11-10',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['planned_quantity', 'target_date']);

        $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
            'planned_quantity' => 11,
            'target_date' => '2026-11-20',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Jumlah rencana produksi melebihi sisa item pesanan (10).');

        $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
            'planned_quantity' => 'dua',
            'target_date' => 'bukan-tanggal',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['planned_quantity', 'target_date']);
    }

    public function test_only_owner_and_admin_can_create_spk(): void
    {
        [, $detail] = $this->makeOrderWithBom(quantity: 10);

        foreach (['produksi', 'gudang'] as $role) {
            $this->login($role);

            $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
                'planned_quantity' => 3,
                'target_date' => '2026-11-20',
            ])->assertStatus(403);
        }

        $this->login('admin');
        $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
            'planned_quantity' => 3,
            'target_date' => '2026-11-20',
        ])->assertCreated();

        $this->login('owner');
        $this->postJson('/api/v1/order-items/'.$detail->order_detail_id.'/production-orders', [
            'planned_quantity' => 3,
            'target_date' => '2026-11-20',
        ])->assertCreated();
    }

    public function test_requirements_are_calculated_from_the_batch_planned_quantity(): void
    {
        [, $detail] = $this->makeOrderWithBom(
            quantity: 10,
            bom: [
                ['Kain Katun', 'meter', 12, 5, 1.5],
                ['Benang Jahit', 'rol', 1, 2, 0.25],
            ],
        );

        $productionOrder = $this->makeProductionOrder($detail, 5);

        $this->login('produksi');

        $this->getJson('/api/v1/production-orders/'.$productionOrder->production_order_id.'/requirements')
            ->assertOk()
            ->assertJsonPath('data.production_order_id', $productionOrder->production_order_id)
            ->assertJsonPath('data.planned_quantity', 5)
            ->assertJsonPath('data.is_sufficient', false)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.material_name', 'Benang Jahit')
            ->assertJsonPath('data.items.0.required_quantity', 1.25)
            ->assertJsonPath('data.items.0.current_stock', 1)
            ->assertJsonPath('data.items.0.shortage_quantity', 0.25)
            ->assertJsonPath('data.items.0.is_sufficient', false)
            ->assertJsonPath('data.items.1.material_name', 'Kain Katun')
            ->assertJsonPath('data.items.1.required_quantity', 7.5)
            ->assertJsonPath('data.items.1.current_stock', 12)
            ->assertJsonPath('data.items.1.shortage_quantity', 0)
            ->assertJsonPath('data.items.1.is_sufficient', true);
    }

    public function test_warehouse_issue_materials_writes_keluar_movements_and_starts_production(): void
    {
        [, $detail] = $this->makeOrderWithBom(
            quantity: 10,
            bom: [
                ['Kain Katun', 'meter', 100, 5, 2],
                ['Benang Jahit', 'rol', 10, 2, 0.5],
            ],
        );

        $productionOrder = $this->makeProductionOrder($detail, 5);

        $this->login('gudang');

        $response = $this->postJson(
            '/api/v1/production-orders/'.$productionOrder->production_order_id.'/issue-materials',
        )
            ->assertOk()
            ->assertJsonPath('message', 'Bahan berhasil diserahkan. Status produksi menjadi Berjalan.')
            ->assertJsonPath('data.status', 'Berjalan')
            ->assertJsonCount(2, 'data.stock_movements');

        $this->assertNotNull($response->json('data.start_date'));

        $cotton = Material::where('material_name', 'Kain Katun')->firstOrFail();
        $thread = Material::where('material_name', 'Benang Jahit')->firstOrFail();

        $this->assertDatabaseHas('production_orders', [
            'production_order_id' => $productionOrder->production_order_id,
            'status' => 'Berjalan',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'material_id' => $cotton->material_id,
            'movement_type' => 'KELUAR',
            'quantity' => 10,
            'production_order_id' => $productionOrder->production_order_id,
            'purchase_detail_id' => null,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'material_id' => $thread->material_id,
            'movement_type' => 'KELUAR',
            'quantity' => 2.5,
            'production_order_id' => $productionOrder->production_order_id,
            'purchase_detail_id' => null,
        ]);

        $this->assertSame(90.0, (float) $cotton->refresh()->current_stock);
        $this->assertSame(7.5, (float) $thread->refresh()->current_stock);

        $this->postJson(
            '/api/v1/production-orders/'.$productionOrder->production_order_id.'/issue-materials',
        )->assertStatus(422);

        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame(90.0, (float) $cotton->refresh()->current_stock);
        $this->assertSame(7.5, (float) $thread->refresh()->current_stock);
    }

    public function test_issue_materials_is_rejected_atomically_when_stock_is_insufficient(): void
    {
        [, $detail] = $this->makeOrderWithBom(
            quantity: 10,
            bom: [
                ['Kain Katun', 'meter', 5, 5, 2],
                ['Benang Jahit', 'rol', 10, 2, 0.5],
            ],
        );

        $productionOrder = $this->makeProductionOrder($detail, 5);

        $this->login('gudang');

        $response = $this->postJson(
            '/api/v1/production-orders/'.$productionOrder->production_order_id.'/issue-materials',
        )
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stok bahan tidak mencukupi untuk perintah produksi ini.');

        $errors = $response->json('errors.materials');

        $this->assertIsArray($errors);
        $this->assertStringContainsString('Kain Katun', implode(' ', $errors));

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('production_orders', [
            'production_order_id' => $productionOrder->production_order_id,
            'status' => 'Menunggu',
        ]);
        $this->assertSame(
            5.0,
            (float) Material::where('material_name', 'Kain Katun')->firstOrFail()->current_stock,
        );
        $this->assertSame(
            10.0,
            (float) Material::where('material_name', 'Benang Jahit')->firstOrFail()->current_stock,
        );
    }

    public function test_only_warehouse_and_owner_can_issue_materials(): void
    {
        [, $detail] = $this->makeOrderWithBom(
            quantity: 10,
            bom: [
                ['Kain Katun', 'meter', 100, 5, 2],
                ['Benang Jahit', 'rol', 10, 2, 0.5],
            ],
        );

        $first = $this->makeProductionOrder($detail, 3);
        $second = $this->makeProductionOrder($detail, 3);

        $this->login('admin');
        $this->postJson('/api/v1/production-orders/'.$first->production_order_id.'/issue-materials')
            ->assertStatus(403);

        $this->login('produksi');
        $this->postJson('/api/v1/production-orders/'.$first->production_order_id.'/issue-materials')
            ->assertStatus(403);

        $this->login('gudang');
        $this->postJson('/api/v1/production-orders/'.$first->production_order_id.'/issue-materials')
            ->assertOk();

        $this->login('owner');
        $this->postJson('/api/v1/production-orders/'.$second->production_order_id.'/issue-materials')
            ->assertOk();
    }

    public function test_production_records_progress_and_completes_after_all_four_stages(): void
    {
        [, $detail] = $this->makeOrderWithBom(quantity: 10);
        $productionOrder = $this->makeProductionOrder($detail, 4, 'Berjalan');
        $employee = $this->makeEmployee();

        $this->login('produksi');

        $cutting = $this->postJson(
            '/api/v1/production-orders/'.$productionOrder->production_order_id.'/progress',
            [
                'employee_id' => $employee->employee_id,
                'stage_code' => 'CUTTING',
                'status' => 'Proses',
                'started_at' => '2026-11-10 08:00:00',
            ],
        )
            ->assertCreated()
            ->assertJsonPath('message', 'Progres produksi berhasil dicatat.')
            ->assertJsonPath('data.stage_code', 'CUTTING')
            ->assertJsonPath('data.status', 'Proses')
            ->assertJsonPath('data.completed_at', null)
            ->assertJsonPath('data.employee.name', 'Rudi Produksi');

        $cuttingId = $cutting->json('data.progress_id');

        $this->patchJson('/api/v1/progress/'.$cuttingId, [
            'status' => 'Selesai',
            'completed_at' => '2026-11-10 10:00:00',
            'completed_quantity' => 4,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Progres produksi berhasil diperbarui.')
            ->assertJsonPath('data.status', 'Selesai')
            ->assertJsonPath('data.completed_at', '2026-11-10 10:00:00')
            ->assertJsonPath('data.completed_quantity', 4);

        // Rework is simply a new progress row on the same stage.
        $this->postJson(
            '/api/v1/production-orders/'.$productionOrder->production_order_id.'/progress',
            [
                'employee_id' => $employee->employee_id,
                'stage_code' => 'CUTTING',
                'status' => 'Proses',
                'started_at' => '2026-11-10 10:30:00',
            ],
        )->assertCreated();

        foreach (['SEWING', 'FINISHING'] as $index => $stage) {
            $this->postJson(
                '/api/v1/production-orders/'.$productionOrder->production_order_id.'/progress',
                [
                    'employee_id' => $employee->employee_id,
                    'stage_code' => $stage,
                    'status' => 'Selesai',
                    'started_at' => '2026-11-11 0'.($index + 1).':00:00',
                    'completed_at' => '2026-11-11 0'.($index + 2).':00:00',
                    'completed_quantity' => 4,
                ],
            )->assertCreated();
        }

        $this->assertSame(
            'Berjalan',
            $productionOrder->refresh()->status,
        );

        $this->postJson(
            '/api/v1/production-orders/'.$productionOrder->production_order_id.'/progress',
            [
                'employee_id' => $employee->employee_id,
                'stage_code' => 'QC',
                'status' => 'Selesai',
                'started_at' => '2026-11-12 08:00:00',
                'completed_at' => '2026-11-12 09:00:00',
                'completed_quantity' => 4,
            ],
        )
            ->assertCreated()
            ->assertJsonPath('data.stage_code', 'QC');

        $this->assertSame('Selesai', $productionOrder->refresh()->status);
        $this->assertSame(5, $productionOrder->progress()->count());

        $this->postJson(
            '/api/v1/production-orders/'.$productionOrder->production_order_id.'/progress',
            [
                'employee_id' => $employee->employee_id,
                'stage_code' => 'QC',
                'status' => 'Proses',
                'started_at' => '2026-11-12 09:30:00',
            ],
        )->assertStatus(422);
    }

    public function test_progress_requires_running_production_and_production_role(): void
    {
        [, $detail] = $this->makeOrderWithBom(quantity: 10);
        $waiting = $this->makeProductionOrder($detail, 3);
        $running = $this->makeProductionOrder($detail, 3, 'Berjalan');
        $employee = $this->makeEmployee();

        $payload = [
            'employee_id' => $employee->employee_id,
            'stage_code' => 'CUTTING',
            'status' => 'Selesai',
            'started_at' => '2026-11-10 08:00:00',
            'completed_at' => '2026-11-10 09:00:00',
            'completed_quantity' => 3,
        ];

        $this->login('produksi');

        $this->postJson(
            '/api/v1/production-orders/'.$waiting->production_order_id.'/progress',
            $payload,
        )
            ->assertStatus(422)
            ->assertJsonPath('message', 'Progres hanya dapat dicatat pada perintah produksi berstatus Berjalan.');

        $this->postJson(
            '/api/v1/production-orders/'.$running->production_order_id.'/progress',
            [...$payload, 'stage_code' => 'UNKNOWN'],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['stage_code']);

        $this->postJson(
            '/api/v1/production-orders/'.$running->production_order_id.'/progress',
            [...$payload, 'completed_at' => null],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['completed_at']);

        $this->postJson(
            '/api/v1/production-orders/'.$running->production_order_id.'/progress',
            [...$payload, 'completed_at' => '2026-11-10 07:00:00'],
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['completed_at']);

        $this->login('admin');
        $this->postJson(
            '/api/v1/production-orders/'.$running->production_order_id.'/progress',
            $payload,
        )->assertStatus(403);

        $this->login('gudang');
        $this->postJson(
            '/api/v1/production-orders/'.$running->production_order_id.'/progress',
            $payload,
        )->assertStatus(403);

        $this->login('produksi');
        $created = $this->postJson(
            '/api/v1/production-orders/'.$running->production_order_id.'/progress',
            $payload,
        )->assertCreated();

        $progressId = $created->json('data.progress_id');

        $this->login('admin');
        $this->patchJson('/api/v1/progress/'.$progressId, [
            'completed_quantity' => 4,
        ])->assertStatus(403);

        $this->login('gudang');
        $this->patchJson('/api/v1/progress/'.$progressId, [
            'completed_quantity' => 4,
        ])->assertStatus(403);

        $this->login('produksi');
        $this->patchJson('/api/v1/progress/'.$progressId, [
            'completed_quantity' => 4,
        ])
            ->assertOk()
            ->assertJsonPath('data.completed_quantity', 4);

        $this->login('owner');
        $this->postJson(
            '/api/v1/production-orders/'.$running->production_order_id.'/progress',
            [
                ...$payload,
                'stage_code' => 'SEWING',
                'started_at' => '2026-11-11 08:00:00',
                'completed_at' => '2026-11-11 09:00:00',
            ],
        )->assertCreated();
    }

    public function test_handover_requires_finished_production_and_full_payment(): void
    {
        [$unpaidOrder, $unpaidDetail] = $this->makeOrderWithBom(
            quantity: 5,
            paidAmount: 200000,
        );
        $this->makeProductionOrder($unpaidDetail, 5, 'Selesai');

        $this->login('admin');

        $this->postJson('/api/v1/orders/'.$unpaidOrder->order_id.'/handover')
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Sisa tagihan Rp800.000 harus dilunasi sebelum produk diserahkan.',
            );

        $this->makePayment($unpaidOrder, 800000, 'Pelunasan');

        $this->postJson('/api/v1/orders/'.$unpaidOrder->order_id.'/handover')
            ->assertOk()
            ->assertJsonPath('message', 'Produk berhasil diserahkan. Pesanan telah selesai.')
            ->assertJsonPath('data.status', 'Selesai');

        $this->postJson('/api/v1/orders/'.$unpaidOrder->order_id.'/handover')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Produk untuk pesanan ini sudah diserahkan.');

        [$runningOrder, $runningDetail] = $this->makeOrderWithBom(
            quantity: 5,
            paidAmount: 1000000,
        );
        $this->makeProductionOrder($runningDetail, 5, 'Berjalan');

        $this->postJson('/api/v1/orders/'.$runningOrder->order_id.'/handover')
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Semua perintah produksi pesanan harus berstatus Selesai sebelum produk diserahkan.',
            );

        [$emptyOrder] = $this->makeOrderWithBom(
            quantity: 5,
            paidAmount: 1000000,
        );

        $this->postJson('/api/v1/orders/'.$emptyOrder->order_id.'/handover')
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Pesanan belum memiliki perintah produksi sehingga belum dapat diserahkan.',
            );
    }

    public function test_only_owner_and_admin_can_hand_over_products(): void
    {
        [$firstOrder, $firstDetail] = $this->makeOrderWithBom(quantity: 5, paidAmount: 1000000);
        $this->makeProductionOrder($firstDetail, 5, 'Selesai');

        [$secondOrder, $secondDetail] = $this->makeOrderWithBom(quantity: 5, paidAmount: 1000000);
        $this->makeProductionOrder($secondDetail, 5, 'Selesai');

        [$thirdOrder, $thirdDetail] = $this->makeOrderWithBom(quantity: 5, paidAmount: 1000000);
        $this->makeProductionOrder($thirdDetail, 5, 'Selesai');

        [$fourthOrder, $fourthDetail] = $this->makeOrderWithBom(quantity: 5, paidAmount: 1000000);
        $this->makeProductionOrder($fourthDetail, 5, 'Selesai');

        $this->login('produksi');
        $this->postJson('/api/v1/orders/'.$firstOrder->order_id.'/handover')->assertStatus(403);

        $this->login('gudang');
        $this->postJson('/api/v1/orders/'.$secondOrder->order_id.'/handover')->assertStatus(403);

        $this->login('admin');
        $this->postJson('/api/v1/orders/'.$thirdOrder->order_id.'/handover')
            ->assertOk()
            ->assertJsonPath('data.status', 'Selesai');

        $this->login('owner');
        $this->postJson('/api/v1/orders/'.$fourthOrder->order_id.'/handover')
            ->assertOk()
            ->assertJsonPath('data.status', 'Selesai');
    }

    public function test_production_order_index_supports_status_search_and_pagination(): void
    {
        [$order, $detail] = $this->makeOrderWithBom(quantity: 10);
        $waiting = $this->makeProductionOrder($detail, 3, 'Menunggu');
        $running = $this->makeProductionOrder($detail, 4, 'Berjalan');

        $this->login('produksi');

        $this->getJson('/api/v1/production-orders?status=Berjalan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.production_order_id', $running->production_order_id)
            ->assertJsonPath('data.0.order.order_id', $order->order_id)
            ->assertJsonPath('data.0.item.sku', 'KO-L-HTM');

        $this->getJson('/api/v1/production-orders?search='.$waiting->production_order_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.production_order_id', $waiting->production_order_id);

        $this->getJson('/api/v1/production-orders?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: float, 3: float, 4: float}>  $bom
     * @return array{0: Order, 1: OrderDetail, 2: ProductVariant}
     */
    private function makeOrderWithBom(
        int $quantity = 10,
        string $status = 'Diproses',
        bool $withDp = true,
        float $totalAmount = 1000000,
        float $paidAmount = 100000,
        array $bom = [
            ['Kain Katun', 'meter', 100, 5, 2],
            ['Benang Jahit', 'rol', 10, 2, 0.5],
        ],
    ): array {
        $customer = Customer::firstOrCreate(['name' => 'Toko Berkah Sandang']);
        $product = Product::firstOrCreate(['product_name' => 'Kaos Polos']);
        $variant = ProductVariant::firstOrCreate(
            ['sku' => 'KO-L-HTM'],
            [
                'product_id' => $product->product_id,
                'size' => 'L',
                'color' => 'Hitam',
            ],
        );

        $order = Order::create([
            'customer_id' => $customer->customer_id,
            'order_date' => '2026-11-10',
            'due_date' => '2026-11-30',
            'status' => $status,
            'total_amount' => $totalAmount,
        ]);

        $detail = $order->details()->create([
            'variant_id' => $variant->variant_id,
            'quantity' => $quantity,
            'unit_price' => round($totalAmount / $quantity, 2),
            'subtotal' => $totalAmount,
        ]);

        if ($withDp && $paidAmount > 0) {
            $this->makePayment($order, $paidAmount, 'DP');
        }

        foreach ($bom as [$name, $unit, $stock, $minimum, $perUnit]) {
            $material = Material::firstOrCreate(
                ['material_name' => $name],
                [
                    'unit_name' => $unit,
                    'current_stock' => $stock,
                    'minimum_stock' => $minimum,
                ],
            );

            $variant->materials()->syncWithoutDetaching([
                $material->material_id => ['quantity_per_unit' => $perUnit],
            ]);
        }

        return [$order, $detail, $variant];
    }

    private function makeProductionOrder(
        OrderDetail $detail,
        int $plannedQuantity,
        string $status = 'Menunggu',
        ?string $startDate = null,
    ): ProductionOrder {
        return ProductionOrder::create([
            'order_detail_id' => $detail->order_detail_id,
            'planned_quantity' => $plannedQuantity,
            'status' => $status,
            'start_date' => $startDate,
            'target_date' => '2026-11-30',
        ]);
    }

    private function makePayment(
        Order $order,
        float $amount,
        string $type = 'Pelunasan',
    ): Payment {
        return Payment::create([
            'order_id' => $order->order_id,
            'payment_date' => '2026-11-09',
            'amount' => $amount,
            'payment_method' => 'Tunai',
            'payment_type' => $type,
            'notes' => null,
        ]);
    }

    private function makeEmployee(string $name = 'Rudi Produksi'): Employee
    {
        return Employee::create([
            'name' => $name,
            'phone' => '081200000000',
            'position' => 'Operator',
            'active_status' => true,
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
