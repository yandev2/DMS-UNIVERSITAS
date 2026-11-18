<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! ($user instanceof User)) {
            return;
        }

        $ip = request()?->ip();
        $userAgent = request()?->userAgent();

        activity('auth_audit')
            ->causedBy($user)
            ->performedOn($user)
            ->event('logout')
            ->withProperties([
                'ip' => $ip,
                'user_agent' => $userAgent,
                'guard' => $event->guard,
                'logout_at' => now()->toIso8601String(),
            ])
            ->log("Pengguna {$user->name} ({$user->email}) telah keluar dari sistem (logout).");
    }
}
