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
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('payment_id');
            $table->integer('order_id');
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 20);
            $table->string('payment_type', 20);
            $table->string('notes', 250)->nullable();

            $table->foreign('order_id', 'FK_payments_orders')
                ->references('order_id')
                ->on('orders');
        });

        if ($this->isSqlServer()) {
            DB::statement('ALTER TABLE payments ADD CONSTRAINT CK_payments_amount CHECK (amount > 0)');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT CK_payments_method CHECK (payment_method IN (N'Tunai', N'Transfer', N'QRIS'))");
            DB::statement("ALTER TABLE payments ADD CONSTRAINT CK_payments_type CHECK (payment_type IN (N'DP', N'Cicilan', N'Pelunasan'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }
};
