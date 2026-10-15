<?php

namespace App\Http\Middleware;

use App\Cms\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance mode for the public site (Admin → Settings → Maintenance). Visitors get a 503 page;
 * the admin panel (and its login pages) keep working, and admin users can still see the site.
 * Unlike `php artisan down`, this never locks the admins out.
 */
class FrontendMaintenance
{
    public function __construct(private Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->get('maintenance.enabled') !== true || $this->isAdminArea($request)
            || $request->user()?->can('admin.access')) {
            return $next($request);
        }

        return response()->view('themes.default.maintenance', [
            'message' => $this->settings->translated('maintenance.message'),
        ], 503)->header('Retry-After', '3600');
    }

    private function isAdminArea(Request $request): bool
    {
        $prefix = trim((string) config('cms.admin_prefix'), '/');

        return $request->is($prefix, $prefix.'/*');
    }
}
