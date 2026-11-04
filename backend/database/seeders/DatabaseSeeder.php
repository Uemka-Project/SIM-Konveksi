<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrator sistem informasi produksi konveksi.',
            ],
            [
                'name' => 'Manajer Produksi',
                'slug' => 'manajer-produksi',
                'description' => 'Mengelola perencanaan dan pemantauan produksi.',
            ],
            [
                'name' => 'Staf Produksi',
                'slug' => 'staf-produksi',
                'description' => 'Mencatat dan memperbarui progres produksi.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@konveksi.test'],
            [
                'name' => 'Administrator',
                'password' => 'Admin123!',
                'email_verified_at' => now(),
            ],
        );

        $admin->roles()->syncWithoutDetaching([
            Role::where('slug', 'admin')->value('id'),
        ]);
    }
}
