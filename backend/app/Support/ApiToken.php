<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

class ApiToken
{
    /**
     * Token lifetime in hours.
     */
    public const TTL_HOURS = 8;

    /**
     * Create a signed API token for the given user.
     */
    public static function issue(User $user): string
    {
        $payload = [
            'sub' => $user->getKey(),
            'iat' => now()->getTimestamp(),
            'exp' => now()->addHours(self::TTL_HOURS)->getTimestamp(),
            'nonce' => Str::random(32),
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Decode a token and return its payload when it is valid.
     *
     * @return array{sub: int|string, iat: int, exp: int, nonce: string}|null
     */
    public static function decode(string $token): ?array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload) || ! isset($payload['sub'], $payload['exp'])) {
            return null;
        }

        if ((int) $payload['exp'] <= now()->getTimestamp()) {
            return null;
        }

        return $payload;
    }
}
