<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Authenticate the request using a bearer API token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $payload = $token ? ApiToken::decode($token) : null;

        if (! $payload) {
            return $this->unauthorized();
        }

        $user = User::with('roles')->find($payload['sub']);

        if (! $user || ! $user->is_active || ! $user->isAdmin()) {
            return $this->unauthorized();
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    /**
     * Return a JSON response for unauthenticated requests.
     */
    private function unauthorized(): Response
    {
        return response()->json([
            'message' => 'Sesi login tidak valid atau sudah berakhir. Silakan login kembali.',
        ], 401);
    }
}
