<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Log;

/**
 * Logs failed login attempts and lockouts (too many attempts) to the application log (spec §7).
 */
class LogFailedLogin
{
    public function handle(Failed|Lockout $event): void
    {
        $request = $event instanceof Lockout ? $event->request : request();

        Log::warning($event instanceof Lockout ? 'Login locked out after too many attempts.' : 'Failed login attempt.', [
            'email' => $event instanceof Failed ? ($event->credentials['email'] ?? null) : $request->input('email'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
