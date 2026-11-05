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
        Schema::create('purchases', function (Blueprint $table) {
            $table->increments('purchase_id');
            $table->integer('supplier_id');
            $table->date('purchase_date');
            $table->string('status', 20)->default('Dipesan');
            $table->decimal('total_amount', 14, 2)->default(0);

            $table->foreign('supplier_id', 'FK_purchases_suppliers')
                ->references('supplier_id')
                ->on('suppliers');
        });

        Schema::create('purchase_details', function (Blueprint $table) {
            $table->increments('purchase_detail_id');
            $table->integer('purchase_id');
            $table->integer('material_id');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 14, 2);

            $table->foreign('purchase_id', 'FK_pd_purchases')
                ->references('purchase_id')
                ->on('purchases');
            $table->foreign('material_id', 'FK_pd_materials')
                ->references('material_id')
                ->on('materials');
        });

        if ($this->isSqlServer()) {
            DB::statement("ALTER TABLE purchases ADD CONSTRAINT CK_purchases_status CHECK (status IN (N'Dipesan', N'Diterima', N'Dibatalkan'))");
            DB::statement('ALTER TABLE purchases ADD CONSTRAINT CK_purchases_total CHECK (total_amount >= 0)');
            DB::statement('ALTER TABLE purchase_details ADD CONSTRAINT CK_pd_qty CHECK (quantity > 0)');
            DB::statement('ALTER TABLE purchase_details ADD CONSTRAINT CK_pd_price CHECK (unit_price >= 0)');
            DB::statement('ALTER TABLE purchase_details ADD CONSTRAINT CK_pd_subtotal CHECK (subtotal = quantity * unit_price)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_details');
        Schema::dropIfExists('purchases');
    }

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }
};
