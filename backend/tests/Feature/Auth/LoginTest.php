<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_username(): void
    {
        $user = $this->createUserWithRole('admin', [
            'username' => 'dewi',
            'email' => 'dewi@konveksisejahtera.test',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'dewi',
            'password' => 'Admin123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonPath('data.user.username', 'dewi')
            ->assertJsonPath('data.user.email', 'dewi@konveksisejahtera.test')
            ->assertJsonPath('data.user.roles.0.role_name', 'admin')
            ->assertJsonPath('data.user.permissions.customers', 'CRUD')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['user_id', 'name', 'username', 'email', 'roles', 'permissions'],
                ],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->user_id,
            'name' => 'spa',
        ]);
    }

    public function test_user_can_login_with_email(): void
    {
        $this->createUserWithRole('admin', [
            'username' => 'dewi',
            'email' => 'dewi@konveksisejahtera.test',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'dewi@konveksisejahtera.test',
            'password' => 'Admin123!',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.username', 'dewi');
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $this->createUserWithRole('admin', [
            'username' => 'dewi',
            'email' => 'dewi@konveksisejahtera.test',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'dewi',
            'password' => 'password-salah',
        ])->assertStatus(401)
            ->assertJsonPath('message', 'Username/email atau password yang Anda masukkan salah.');
    }

    public function test_login_requires_login_and_password(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login', 'password']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->createUserWithRole('admin', [
            'username' => 'dewi',
            'email' => 'dewi@konveksisejahtera.test',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'dewi',
            'password' => 'Admin123!',
        ])->assertStatus(403);
    }

    public function test_non_admin_role_can_still_login_and_receives_limited_permissions(): void
    {
        $this->createUserWithRole('produksi', [
            'username' => 'rudi',
            'email' => 'rudi@konveksisejahtera.test',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'rudi',
            'password' => 'Admin123!',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.permissions.customers', 'R')
            ->assertJsonPath('data.user.permissions.users', '');
    }

    public function test_authenticated_user_can_fetch_profile_with_token(): void
    {
        $this->createUserWithRole('admin', [
            'username' => 'dewi',
            'email' => 'dewi@konveksisejahtera.test',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'dewi',
            'password' => 'Admin123!',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.username', 'dewi')
            ->assertJsonPath('data.roles.0.role_name', 'admin')
            ->assertJsonPath('data.permissions.products', 'R');
    }

    public function test_user_can_logout_and_token_is_revoked(): void
    {
        $this->createUserWithRole('admin', [
            'username' => 'dewi',
            'email' => 'dewi@konveksisejahtera.test',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'dewi',
            'password' => 'Admin123!',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout berhasil.');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // The test harness reuses the application instance, so clear the cached guard
        // before checking that the revoked token is rejected.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_profile_cannot_be_fetched_with_an_invalid_token(): void
    {
        $this->withHeader('Authorization', 'Bearer token-palsu')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Sesi login tidak valid atau sudah berakhir. Silakan login kembali.');
    }

    /**
     * Create a user attached to the given role.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createUserWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(
            ['role_name' => $roleName],
            ['description' => 'Role untuk pengujian.'],
        );

        $user = User::factory()->create([
            ...$attributes,
            'password_hash' => 'Admin123!',
        ]);

        $user->roles()->attach($role);

        return $user;
    }
}
