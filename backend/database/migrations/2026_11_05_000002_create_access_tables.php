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
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('role_id');
            $table->string('role_name', 30);
            $table->string('description', 150)->nullable();

            $table->unique('role_name', 'UQ_roles_name');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->integer('employee_id')->nullable();
            $table->string('username', 50);
            $table->string('password_hash', 255);
            $table->string('email', 100);
            $table->boolean('is_active')->default(true);

            $table->unique('username', 'UQ_users_username');
            $table->unique('email', 'UQ_users_email');
            $table->foreign('employee_id', 'FK_users_employees')
                ->references('employee_id')
                ->on('employees');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->integer('user_id');
            $table->integer('role_id');
            $table->dateTime('assigned_at', 7)->useCurrent();

            $table->primary(['user_id', 'role_id'], 'PK_user_roles');
            $table->foreign('user_id', 'FK_ur_users')
                ->references('user_id')
                ->on('users');
            $table->foreign('role_id', 'FK_ur_roles')
                ->references('role_id')
                ->on('roles');
        });

        if ($this->isSqlServer()) {
            // employees 1 : 1 users, hanya bila employee_id terisi.
            DB::statement(
                'CREATE UNIQUE INDEX UQ_users_employee ON users(employee_id) WHERE employee_id IS NOT NULL'
            );
        } else {
            Schema::table('users', function (Blueprint $table) {
                // SQLite dan MySQL membolehkan banyak NULL pada unique index.
                $table->unique('employee_id', 'UQ_users_employee');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }

    private function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }
};
