<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate with a username or email and issue a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $login = trim($credentials['login']);

        $user = User::with(['roles', 'employee'])
            ->where(function ($query) use ($login): void {
                $query->where('username', $login)
                    ->orWhere('email', $login);
            })
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password_hash)) {
            return response()->json([
                'message' => 'Username/email atau password yang Anda masukkan salah.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Akun ini sedang tidak aktif. Hubungi pemilik untuk mengaktifkan kembali.',
            ], 403);
        }

        $token = $user->createToken('spa');

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'user' => (new UserResource($user))->toArray($request),
            ],
        ]);
    }

    /**
     * Return the currently authenticated user with roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['roles', 'employee']);

        return response()->json([
            'data' => (new UserResource($user))->toArray($request),
        ]);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $user->currentAccessToken();

        // Transient (session) tokens do not have a delete method.
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['message' => 'Logout berhasil.']);
    }
}
