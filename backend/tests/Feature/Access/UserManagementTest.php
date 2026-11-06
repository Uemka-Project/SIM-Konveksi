<?php

namespace Tests\Feature\Access;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_roles_and_users(): void
    {
        foreach (['owner', 'admin', 'gudang', 'produksi'] as $roleName) {
            Role::firstOrCreate(
                ['role_name' => $roleName],
                ['description' => 'Role untuk pengujian.'],
            );
        }

        $owner = $this->userWithRole('owner');
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.username', $owner->username);
    }

    public function test_non_owner_cannot_access_user_management(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->getJson('/api/v1/users')->assertStatus(403);
        $this->getJson('/api/v1/roles')->assertStatus(403);
    }

    public function test_owner_can_create_a_user_with_roles(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $adminRole = Role::where('role_name', 'admin')->firstOrFail();

        $response = $this->postJson('/api/v1/users', [
            'username' => 'dewi',
            'email' => 'dewi@konveksisejahtera.test',
            'password' => 'Rahasia#2026',
            'is_active' => true,
            'role_ids' => [$adminRole->role_id],
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Pengguna berhasil ditambahkan.')
            ->assertJsonPath('data.username', 'dewi')
            ->assertJsonPath('data.role_names.0', 'admin');

        $userId = $response->json('data.user_id');

        $this->assertDatabaseHas('users', [
            'user_id' => $userId,
            'username' => 'dewi',
            'email' => 'dewi@konveksisejahtera.test',
        ]);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $userId,
            'role_id' => $adminRole->role_id,
        ]);
    }

    public function test_user_creation_validates_duplicate_username_and_missing_roles(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        User::factory()->create(['username' => 'dewi']);

        $this->postJson('/api/v1/users', [
            'username' => 'dewi',
            'email' => 'dewi2@konveksisejahtera.test',
            'password' => 'Rahasia#2026',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'role_ids']);
    }

    public function test_owner_can_update_user_and_sync_roles(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $target = $this->userWithRole('admin');
        $gudang = Role::where('role_name', 'gudang')->firstOrFail();

        $this->putJson('/api/v1/users/'.$target->user_id, [
            'username' => $target->username,
            'email' => 'updated@konveksisejahtera.test',
            'is_active' => true,
            'role_ids' => [$gudang->role_id],
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'updated@konveksisejahtera.test')
            ->assertJsonPath('data.role_names.0', 'gudang');

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $target->user_id,
            'role_id' => $gudang->role_id,
        ]);
    }

    public function test_owner_cannot_deactivate_their_own_account(): void
    {
        $owner = $this->userWithRole('owner');
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/users/'.$owner->user_id, [
            'username' => $owner->username,
            'email' => $owner->email,
            'is_active' => false,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Anda tidak dapat menonaktifkan akun sendiri.');
    }

    public function test_deleting_a_user_deactivates_the_account_and_revokes_tokens(): void
    {
        Sanctum::actingAs($this->userWithRole('owner'));

        $target = $this->userWithRole('admin');
        $target->createToken('spa');

        $this->deleteJson('/api/v1/users/'.$target->user_id)
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'user_id' => $target->user_id,
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function userWithRole(string $roleName): User
    {
        foreach (['owner', 'admin', 'gudang', 'produksi'] as $name) {
            Role::firstOrCreate(
                ['role_name' => $name],
                ['description' => 'Role untuk pengujian.'],
            );
        }

        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $roleName)->firstOrFail());

        return $user;
    }
}
