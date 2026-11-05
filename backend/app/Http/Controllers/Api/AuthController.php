<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate an administrator and issue an API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::with(['roles', 'employee'])
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password_hash)) {
            return response()->json([
                'message' => 'Email atau password yang Anda masukkan salah.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun ini sedang tidak aktif.',
            ], 403);
        }

        if (! $user->isAdmin()) {
            return response()->json([
                'message' => 'Akun ini tidak memiliki akses sebagai Admin.',
            ], 403);
        }

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'token' => ApiToken::issue($user),
                'token_type' => 'Bearer',
                'expires_in' => ApiToken::TTL_HOURS * 60 * 60,
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    /**
     * Return the currently authenticated administrator.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    /**
     * Build the user payload returned to the frontend.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->employee?->name ?? $user->username,
            'username' => $user->username,
            'email' => $user->email,
            'roles' => $user->roles
                ->map(fn ($role) => [
                    'id' => $role->getKey(),
                    'name' => $role->role_name,
                    'slug' => $role->role_name,
                ])
                ->values()
                ->all(),
        ];
    }
}
