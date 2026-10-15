<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware: browser security headers on every response.
 *
 * - no MIME sniffing, no framing by other sites (clickjacking), a strict referrer policy;
 * - no camera, microphone or location access for the pages;
 * - HSTS on HTTPS requests in production (browsers then refuse plain HTTP for a year).
 *
 * A Content-Security-Policy is left to the theme/server: the admin and TinyMCE rely on inline
 * scripts and styles, so a useful CSP needs nonces throughout the admin first.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
