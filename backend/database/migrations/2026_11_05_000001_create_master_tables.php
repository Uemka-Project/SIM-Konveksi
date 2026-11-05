<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('customer_id');
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('address', 250)->nullable();
            $table->string('email', 100)->nullable();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->increments('supplier_id');
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('address', 250)->nullable();
            $table->string('email', 100)->nullable();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->increments('employee_id');
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('position', 50);
            $table->boolean('active_status')->default(true);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->increments('material_id');
            $table->string('material_name', 100);
            $table->string('unit_name', 20);
            $table->decimal('current_stock', 12, 2)->default(0);
            $table->decimal('minimum_stock', 12, 2)->default(0);

            $table->unique('material_name', 'UQ_materials_name');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->increments('product_id');
            $table->string('product_name', 100);
            $table->string('description', 250)->nullable();

            $table->unique('product_name', 'UQ_products_name');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->increments('variant_id');
            $table->integer('product_id');
            $table->string('size', 10);
            $table->string('color', 30);
            $table->string('sku', 30);

            $table->unique('sku', 'UQ_product_variants_sku');
            $table->foreign('product_id', 'FK_variants_products')
                ->references('product_id')
                ->on('products');
        });

        Schema::create('variant_materials', function (Blueprint $table) {
            $table->integer('variant_id');
            $table->integer('material_id');
            $table->decimal('quantity_per_unit', 10, 3);

            $table->primary(['variant_id', 'material_id'], 'PK_variant_materials');
            $table->foreign('variant_id', 'FK_vm_variants')
                ->references('variant_id')
                ->on('product_variants');
            $table->foreign('material_id', 'FK_vm_materials')
                ->references('material_id')
                ->on('materials');
        });

        if ($this->isSqlServer()) {
            DB::statement('ALTER TABLE materials ADD CONSTRAINT CK_materials_stock CHECK (current_stock >= 0)');
            DB::statement('ALTER TABLE materials ADD CONSTRAINT CK_materials_min CHECK (minimum_stock >= 0)');
            DB::statement('ALTER TABLE variant_materials ADD CONSTRAINT CK_vm_qty CHECK (quantity_per_unit > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_materials');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }
};
