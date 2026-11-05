<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    /**
     * Password awal semua akun: Konveksi#2026
     */
    private const PASSWORD_HASH = '$2y$10$87GvQoQ6GF09mIIs07w0KOW1rXT2zjj.f88QazQvtcbwIZ1BhZ5eG';

    /**
     * Seed roles and initial users from docs/03_seed_akses.sql.
     */
    public function run(): void
    {
        $roles = [
            ['role_name' => 'owner', 'description' => 'Pemilik: semua modul, laporan, dan kelola pengguna'],
            ['role_name' => 'admin', 'description' => 'Admin/kasir: pelanggan, pesanan, pembayaran, pembelian'],
            ['role_name' => 'produksi', 'description' => 'Kepala produksi: perintah produksi dan progres'],
            ['role_name' => 'gudang', 'description' => 'Staf gudang: bahan, penerimaan pembelian, stok'],
        ];

        $roleModels = [];

        foreach ($roles as $role) {
            $roleModels[$role['role_name']] = Role::updateOrCreate(
                ['role_name' => $role['role_name']],
                ['description' => $role['description']],
            );
        }

        $users = [
            ['username' => 'owner', 'email' => 'owner@konveksisejahtera.test', 'employee' => null, 'role' => 'owner'],
            ['username' => 'dewi', 'email' => 'dewi@konveksisejahtera.test', 'employee' => 'Dewi Lestari', 'role' => 'admin'],
            ['username' => 'budi', 'email' => 'budi@konveksisejahtera.test', 'employee' => 'Budi Santoso', 'role' => 'gudang'],
            ['username' => 'rudi', 'email' => 'rudi@konveksisejahtera.test', 'employee' => 'Rudi Hartono', 'role' => 'produksi'],
        ];

        foreach ($users as $data) {
            $employee = $data['employee']
                ? Employee::where('name', $data['employee'])->firstOrFail()
                : null;

            $user = User::updateOrCreate(
                ['username' => $data['username']],
                [
                    'employee_id' => $employee?->employee_id,
                    'password_hash' => self::PASSWORD_HASH,
                    'email' => $data['email'],
                    'is_active' => true,
                ],
            );

            $user->roles()->sync([$roleModels[$data['role']]->role_id]);
        }
    }
}
