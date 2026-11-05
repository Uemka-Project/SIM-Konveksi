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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->increments('production_order_id');
            $table->integer('order_detail_id');
            $table->integer('planned_quantity');
            $table->string('status', 20)->default('Menunggu');
            $table->date('start_date')->nullable();
            $table->date('target_date');

            $table->foreign('order_detail_id', 'FK_po_order_details')
                ->references('order_detail_id')
                ->on('order_details');
        });

        Schema::create('production_progress', function (Blueprint $table) {
            $table->increments('progress_id');
            $table->integer('production_order_id');
            $table->integer('employee_id');
            $table->string('stage_code', 20);
            $table->string('status', 20)->default('Proses');
            $table->dateTime('started_at', 7);
            $table->dateTime('completed_at', 7)->nullable();
            $table->integer('completed_quantity')->default(0);

            $table->foreign('production_order_id', 'FK_pp_production_orders')
                ->references('production_order_id')
                ->on('production_orders');
            $table->foreign('employee_id', 'FK_pp_employees')
                ->references('employee_id')
                ->on('employees');
        });

        if ($this->isSqlServer()) {
            DB::statement('ALTER TABLE production_orders ADD CONSTRAINT CK_po_qty CHECK (planned_quantity > 0)');
            DB::statement("ALTER TABLE production_orders ADD CONSTRAINT CK_po_status CHECK (status IN (N'Menunggu', N'Berjalan', N'Selesai'))");
            DB::statement('ALTER TABLE production_orders ADD CONSTRAINT CK_po_dates CHECK (start_date IS NULL OR target_date >= start_date)');
            DB::statement("ALTER TABLE production_progress ADD CONSTRAINT CK_pp_stage CHECK (stage_code IN (N'CUTTING', N'SEWING', N'FINISHING', N'QC'))");
            DB::statement("ALTER TABLE production_progress ADD CONSTRAINT CK_pp_status CHECK (status IN (N'Proses', N'Selesai'))");
            DB::statement('ALTER TABLE production_progress ADD CONSTRAINT CK_pp_qty CHECK (completed_quantity >= 0)');
            DB::statement(
                "ALTER TABLE production_progress ADD CONSTRAINT CK_pp_done CHECK ("
                ."(status = N'Selesai' AND completed_at IS NOT NULL AND completed_at >= started_at) OR "
                ."(status = N'Proses' AND completed_at IS NULL))"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_progress');
        Schema::dropIfExists('production_orders');
    }

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }
};
