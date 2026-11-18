<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! ($user instanceof User)) {
            return;
        }

        $ip = request()?->ip();
        $userAgent = request()?->userAgent();

        // Perbarui atribut last_login pada pengguna tanpa memicu event updated model
        $user->timestamps = false;
        $user->last_login_at = now();
        $user->last_login_ip = $ip;
        $user->saveQuietly();
        $user->timestamps = true;

        activity('auth_audit')
            ->causedBy($user)
            ->performedOn($user)
            ->event('login')
            ->withProperties([
                'ip' => $ip,
                'user_agent' => $userAgent,
                'guard' => $event->guard,
                'login_at' => now()->toIso8601String(),
            ])
            ->log("Pengguna {$user->name} ({$user->email}) berhasil masuk ke sistem.");
    }
}
