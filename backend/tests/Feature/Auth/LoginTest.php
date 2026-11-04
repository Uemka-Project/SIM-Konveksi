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
            'name' => 'Administrator',
            'email' => 'admin@konveksi.test',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@konveksi.test',
            'password' => 'Admin123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonPath('data.user.email', 'admin@konveksi.test')
            ->assertJsonPath('data.user.roles.0.slug', 'admin')
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'expires_in',
                    'user' => ['id', 'name', 'email', 'roles'],
                ],
            ]);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $this->createUserWithRole('admin', [
            'email' => 'admin@konveksi.test',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@konveksi.test',
            'password' => 'password-salah',
        ])->assertStatus(401);
    }

    public function test_non_admin_cannot_login(): void
    {
        $this->createUserWithRole('staf-produksi', [
            'email' => 'staf@konveksi.test',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'staf@konveksi.test',
            'password' => 'Admin123!',
        ])->assertStatus(403);
    }

    public function test_admin_can_fetch_profile_with_token(): void
    {
        $this->createUserWithRole('admin', [
            'name' => 'Administrator',
            'email' => 'admin@konveksi.test',
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'admin@konveksi.test',
            'password' => 'Admin123!',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'admin@konveksi.test')
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
    private function createUserWithRole(string $roleSlug, array $attributes = []): User
    {
        $role = Role::create([
            'name' => ucfirst($roleSlug),
            'slug' => $roleSlug,
        ]);

        $user = User::factory()->create([
            ...$attributes,
            'password' => 'Admin123!',
        ]);

        $user->roles()->attach($role);

        return $user;
    }
}
