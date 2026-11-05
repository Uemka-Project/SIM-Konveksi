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
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('order_id');
            $table->integer('customer_id');
            $table->date('order_date');
            $table->date('due_date');
            $table->string('status', 20)->default('Baru');
            $table->decimal('total_amount', 14, 2)->default(0);

            $table->foreign('customer_id', 'FK_orders_customers')
                ->references('customer_id')
                ->on('customers');
        });

        Schema::create('order_details', function (Blueprint $table) {
            $table->increments('order_detail_id');
            $table->integer('order_id');
            $table->integer('variant_id');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 14, 2);

            $table->foreign('order_id', 'FK_od_orders')
                ->references('order_id')
                ->on('orders');
            $table->foreign('variant_id', 'FK_od_variants')
                ->references('variant_id')
                ->on('product_variants');
        });

        if ($this->isSqlServer()) {
            DB::statement("ALTER TABLE orders ADD CONSTRAINT CK_orders_status CHECK (status IN (N'Baru', N'Diproses', N'Selesai', N'Dibatalkan'))");
            DB::statement('ALTER TABLE orders ADD CONSTRAINT CK_orders_dates CHECK (due_date >= order_date)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT CK_orders_total CHECK (total_amount >= 0)');
            DB::statement('ALTER TABLE order_details ADD CONSTRAINT CK_od_qty CHECK (quantity > 0)');
            DB::statement('ALTER TABLE order_details ADD CONSTRAINT CK_od_price CHECK (unit_price >= 0)');
            DB::statement('ALTER TABLE order_details ADD CONSTRAINT CK_od_subtotal CHECK (subtotal = quantity * unit_price)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_details');
        Schema::dropIfExists('orders');
    }

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }
};
