<?php

namespace Tests\Feature\Master;

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BomTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_save_bom_for_a_variant(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        [$product, $variant] = $this->makeProductWithVariant();

        $material = Material::create([
            'material_name' => 'Kain Katun',
            'unit_name' => 'meter',
            'current_stock' => 25,
            'minimum_stock' => 10,
        ]);

        $this->putJson("/api/v1/variants/{$variant->variant_id}/bom", [
            'items' => [
                ['material_id' => $material->material_id, 'quantity_per_unit' => 2.5],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'BOM varian berhasil disimpan.')
            ->assertJsonPath('data.items.0.material_id', $material->material_id)
            ->assertJsonPath('data.items.0.quantity_per_unit', 2.5);

        $this->assertDatabaseHas('variant_materials', [
            'variant_id' => $variant->variant_id,
            'material_id' => $material->material_id,
            'quantity_per_unit' => 2.5,
        ]);
    }

    public function test_bom_can_be_read_with_material_details(): void
    {
        Sanctum::actingAs($this->userWithRole('produksi'));

        [$product, $variant] = $this->makeProductWithVariant();

        $material = Material::create([
            'material_name' => 'Benang',
            'unit_name' => 'roll',
            'current_stock' => 3,
            'minimum_stock' => 5,
        ]);

        $variant->materials()->attach($material->material_id, ['quantity_per_unit' => 1]);

        $this->getJson("/api/v1/variants/{$variant->variant_id}/bom")
            ->assertOk()
            ->assertJsonPath('data.items.0.material_name', 'Benang')
            ->assertJsonPath('data.items.0.quantity_per_unit', 1)
            ->assertJsonPath('data.variant.sku', 'KMJ-L-PTH');
    }

    public function test_produksi_can_update_bom_but_cannot_delete_variant(): void
    {
        $user = $this->userWithRole('produksi');
        Sanctum::actingAs($user);

        [$product, $variant] = $this->makeProductWithVariant();

        $material = Material::create([
            'material_name' => 'Kain Katun',
            'unit_name' => 'meter',
            'current_stock' => 10,
            'minimum_stock' => 5,
        ]);

        $this->putJson("/api/v1/variants/{$variant->variant_id}/bom", [
            'items' => [
                ['material_id' => $material->material_id, 'quantity_per_unit' => 1],
            ],
        ])->assertOk();

        $this->deleteJson("/api/v1/products/{$product->product_id}/variants/{$variant->variant_id}")
            ->assertStatus(403);
    }

    public function test_gudang_cannot_update_bom(): void
    {
        Sanctum::actingAs($this->userWithRole('gudang'));

        [$product, $variant] = $this->makeProductWithVariant();

        $material = Material::create([
            'material_name' => 'Kain Katun',
            'unit_name' => 'meter',
            'current_stock' => 10,
            'minimum_stock' => 5,
        ]);

        $this->putJson("/api/v1/variants/{$variant->variant_id}/bom", [
            'items' => [
                ['material_id' => $material->material_id, 'quantity_per_unit' => 1],
            ],
        ])->assertStatus(403);
    }

    public function test_bom_rejects_duplicate_material_and_non_positive_quantity(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        [$product, $variant] = $this->makeProductWithVariant();

        $material = Material::create([
            'material_name' => 'Kain Katun',
            'unit_name' => 'meter',
            'current_stock' => 10,
            'minimum_stock' => 5,
        ]);

        $this->putJson("/api/v1/variants/{$variant->variant_id}/bom", [
            'items' => [
                ['material_id' => $material->material_id, 'quantity_per_unit' => 1],
                ['material_id' => $material->material_id, 'quantity_per_unit' => 2],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.material_id', 'items.1.material_id']);

        $this->putJson("/api/v1/variants/{$variant->variant_id}/bom", [
            'items' => [
                ['material_id' => $material->material_id, 'quantity_per_unit' => 0],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity_per_unit']);
    }

    public function test_product_detail_includes_variants(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        [$product, $variant] = $this->makeProductWithVariant();

        $this->getJson('/api/v1/products/'.$product->product_id)
            ->assertOk()
            ->assertJsonPath('data.product_name', 'Kemeja')
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonPath('data.variants.0.sku', 'KMJ-L-PTH');
    }

    /**
     * @return array{0: Product, 1: ProductVariant}
     */
    private function makeProductWithVariant(): array
    {
        $product = Product::create([
            'product_name' => 'Kemeja',
            'description' => 'Kemeja seragam',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->product_id,
            'size' => 'L',
            'color' => 'Putih',
            'sku' => 'KMJ-L-PTH',
        ]);

        return [$product, $variant];
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
