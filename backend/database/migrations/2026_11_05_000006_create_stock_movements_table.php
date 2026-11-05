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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->increments('movement_id');
            $table->integer('material_id');
            $table->string('movement_type', 10);
            $table->decimal('quantity', 12, 2);
            $table->dateTime('movement_date', 7);
            $table->integer('purchase_detail_id')->nullable();
            $table->integer('production_order_id')->nullable();
            $table->string('notes', 250)->nullable();

            $table->foreign('material_id', 'FK_sm_materials')
                ->references('material_id')
                ->on('materials');
            $table->foreign('purchase_detail_id', 'FK_sm_purchase_details')
                ->references('purchase_detail_id')
                ->on('purchase_details');
            $table->foreign('production_order_id', 'FK_sm_production_orders')
                ->references('production_order_id')
                ->on('production_orders');
        });

        if ($this->isSqlServer()) {
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT CK_sm_type CHECK (movement_type IN (N'MASUK', N'KELUAR'))");
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT CK_sm_qty CHECK (quantity > 0)');
            DB::statement(
                'ALTER TABLE stock_movements ADD CONSTRAINT CK_sm_source CHECK ('
                ."(movement_type = N'MASUK' AND purchase_detail_id IS NOT NULL AND production_order_id IS NULL) OR "
                ."(movement_type = N'KELUAR' AND production_order_id IS NOT NULL AND purchase_detail_id IS NULL))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }
};
