<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('product_id', 'IX_variants_product');
        });

        Schema::table('variant_materials', function (Blueprint $table) {
            $table->index('material_id', 'IX_vm_material');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'due_date'], 'IX_orders_status_due');
            $table->index('customer_id', 'IX_orders_customer');
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->index('order_id', 'IX_od_order');
            $table->index('variant_id', 'IX_od_variant');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('order_id', 'IX_payments_order');
            $table->index('payment_date', 'IX_payments_date');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index(['status', 'purchase_date'], 'IX_purchases_status_date');
            $table->index('supplier_id', 'IX_purchases_supplier');
        });

        Schema::table('purchase_details', function (Blueprint $table) {
            $table->index('purchase_id', 'IX_pd_purchase');
            $table->index('material_id', 'IX_pd_material');
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->index('order_detail_id', 'IX_po_order_detail');
            $table->index(['status', 'target_date'], 'IX_po_status_target');
        });

        Schema::table('production_progress', function (Blueprint $table) {
            $table->index('production_order_id', 'IX_pp_production_order');
            $table->index('employee_id', 'IX_pp_employee');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['material_id', 'movement_date'], 'IX_sm_material_date');
            $table->index('purchase_detail_id', 'IX_sm_purchase_detail');
            $table->index('production_order_id', 'IX_sm_production_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('IX_sm_material_date');
            $table->dropIndex('IX_sm_purchase_detail');
            $table->dropIndex('IX_sm_production_order');
        });

        Schema::table('production_progress', function (Blueprint $table) {
            $table->dropIndex('IX_pp_production_order');
            $table->dropIndex('IX_pp_employee');
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropIndex('IX_po_order_detail');
            $table->dropIndex('IX_po_status_target');
        });

        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropIndex('IX_pd_purchase');
            $table->dropIndex('IX_pd_material');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('IX_purchases_status_date');
            $table->dropIndex('IX_purchases_supplier');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('IX_payments_order');
            $table->dropIndex('IX_payments_date');
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropIndex('IX_od_order');
            $table->dropIndex('IX_od_variant');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('IX_orders_status_due');
            $table->dropIndex('IX_orders_customer');
        });

        Schema::table('variant_materials', function (Blueprint $table) {
            $table->dropIndex('IX_vm_material');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('IX_variants_product');
        });
    }
};
