<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        $identifier = $event->credentials['email'] ?? $event->credentials['nidn_nip'] ?? 'tidak diketahui';
        $ip = request()?->ip();
        $userAgent = request()?->userAgent();

        activity('auth_audit')
            ->event('failed_login')
            ->withProperties([
                'identifier' => $identifier,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'guard' => $event->guard,
                'attempted_at' => now()->toIso8601String(),
            ])
            ->log("Percobaan masuk ke sistem gagal untuk identitas: {$identifier}.");
    }
}
