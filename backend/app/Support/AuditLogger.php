<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Minimal audit trail for status-changing operations (PRD section 6).
 *
 * Audit entries are written to a dedicated daily log file instead of a new
 * database table so the existing schema stays untouched.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(string $event, string $description, array $context = []): void
    {
        $user = auth()->user();

        Log::channel('audit')->info($event, [
            'event' => $event,
            'description' => $description,
            'user_id' => $user?->getKey(),
            'username' => $user?->username,
            'ip' => request()?->ip(),
            'context' => $context,
        ]);
    }
}
