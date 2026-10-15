<?php

namespace App\Http\Middleware;

use App\Cms\Support\LocalizedUrls;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware alias "locale" (also in the web group): picks the language of the request.
 *
 * Order: the frontend URL prefix (/ru/…, set by LocalePrefix); ?locale= (remembered in the session,
 * and saved as the user's preference); the session; the user's locale; config('cms.default_locale').
 * Only config('cms.locales') are accepted.
 */
class SetLocale
{
    public const SESSION_KEY = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locales = (array) config('cms.locales');

        $prefix = $request->attributes->get(LocalizedUrls::ATTRIBUTE);
        if (is_string($prefix) && in_array($prefix, $locales, true)) {
            app()->setLocale($prefix);

            return $next($request);
        }

        $user = $request->user();

        $requested = $request->query('locale');
        if (is_string($requested) && in_array($requested, $locales, true)) {
            $request->session()->put(self::SESSION_KEY, $requested);

            if ($user instanceof User && $user->locale !== $requested) {
                $user->forceFill(['locale' => $requested])->saveQuietly();
            }
        }

        $locale = collect([
            $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null,
            $user instanceof User ? $user->locale : null,
        ])->first(fn ($candidate) => is_string($candidate) && in_array($candidate, $locales, true))
            ?? (string) config('cms.default_locale');

        app()->setLocale($locale);

        return $next($request);
    }
}
