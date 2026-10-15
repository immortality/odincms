<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware alias "admin": the user may use the admin panel (spec §6).
 * Runs after "auth": the user is logged in, active and has the admin.access permission.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        // Deactivated while logged in: end the session.
        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('This account has been deactivated.')]);
        }

        if (! $user->can('admin.access')) {
            abort(403);
        }

        return $next($request);
    }
}
