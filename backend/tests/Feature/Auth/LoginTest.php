<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $this->createUserWithRole('admin', [
            'username' => 'dewi',
            'email' => 'dewi@konveksisejahtera.test',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'dewi@konveksisejahtera.test',
            'password' => 'Admin123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonPath('data.user.email', 'dewi@konveksisejahtera.test')
            ->assertJsonPath('data.user.username', 'dewi')
            ->assertJsonPath('data.user.roles.0.slug', 'admin')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'expires_in',
                    'user' => ['id', 'name', 'username', 'email', 'roles'],
                ],
            ]);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $this->createUserWithRole('admin', [
            'email' => 'dewi@konveksisejahtera.test',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'dewi@konveksisejahtera.test',
            'password' => 'password-salah',
        ])->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->createUserWithRole('admin', [
            'email' => 'dewi@konveksisejahtera.test',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'dewi@konveksisejahtera.test',
            'password' => 'Admin123!',
        ])->assertStatus(403);
    }

    public function test_non_admin_cannot_login(): void
    {
        $this->createUserWithRole('produksi', [
            'username' => 'rudi',
            'email' => 'rudi@konveksisejahtera.test',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'rudi@konveksisejahtera.test',
            'password' => 'Admin123!',
        ])->assertStatus(403);
    }

    public function test_admin_can_fetch_profile_with_token(): void
    {
        $this->createUserWithRole('admin', [
            'username' => 'dewi',
            'email' => 'dewi@konveksisejahtera.test',
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'dewi@konveksisejahtera.test',
            'password' => 'Admin123!',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'dewi@konveksisejahtera.test')
            ->assertJsonPath('data.user.roles.0.slug', 'admin');
    }

    public function test_profile_cannot_be_fetched_with_an_invalid_token(): void
    {
        $this->withHeader('Authorization', 'Bearer token-palsu')
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    /**
     * Create a user attached to the given role.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createUserWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::create([
            'role_name' => $roleName,
            'description' => 'Role untuk pengujian.',
        ]);

        $user = User::factory()->create([
            ...$attributes,
            'password_hash' => 'Admin123!',
        ]);

        $user->roles()->attach($role);

        return $user;
    }
}
