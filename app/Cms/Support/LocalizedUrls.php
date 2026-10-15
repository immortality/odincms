<?php

namespace App\Cms\Support;

use Illuminate\Http\Request;

/**
 * Locale prefixes of frontend URLs (spec §6): the default locale has none (/blog/news), the other
 * active locales do (/ru/blog/news). The admin panel and config('cms.unlocalized_paths') never do.
 *
 * Incoming: the LocalePrefix middleware removes the prefix before routing, so every route is
 * defined once and keeps its name. Outgoing: formatPath() is the URL generator's path formatter,
 * so route() and url() add the current locale's prefix by themselves.
 */
final class LocalizedUrls
{
    /** Request attribute holding the locale taken from the URL prefix. */
    public const ATTRIBUTE = 'cms.locale_prefix';

    /**
     * Does $locale get a URL prefix?
     */
    public static function prefixed(string $locale): bool
    {
        return $locale !== config('cms.default_locale') && in_array($locale, (array) config('cms.locales'), true);
    }

    /**
     * Can the path (without prefix) be localized? Not the admin panel, assets, the health check, …
     */
    public static function localizable(string $path): bool
    {
        $first = explode('/', trim($path, '/'), 2)[0];
        $excluded = [trim((string) config('cms.admin_prefix'), '/'), ...(array) config('cms.unlocalized_paths')];

        return $first === '' || ! in_array($first, $excluded, true);
    }

    /**
     * Add $locale's prefix (default: the current locale) to a path: "/blog" → "/ru/blog", "/" → "/ru".
     */
    public static function formatPath(string $path, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if (! self::prefixed($locale) || ! self::localizable($path)) {
            return $path;
        }

        return '/'.$locale.(trim($path, '/') === '' ? '' : '/'.ltrim($path, '/'));
    }

    /**
     * The current page in another locale (language switcher, hreflang links).
     *
     * The default locale's URL carries ?locale=, which also replaces a language remembered in the
     * session (unprefixed URLs fall back to it).
     */
    public static function current(string $locale, ?Request $request = null): string
    {
        $request ??= request();
        $query = $request->query();
        unset($query['locale'], $query['page']);

        if (! self::prefixed($locale) && self::localizable($request->getPathInfo())) {
            $query['locale'] = $locale;
        }

        return $request->getSchemeAndHttpHost().$request->getBaseUrl()
            .self::formatPath($request->getPathInfo(), $locale)
            .($query !== [] ? '?'.http_build_query($query) : '');
    }

    /**
     * The canonical URLs of the current page in every active locale (for hreflang), locale => URL.
     *
     * @return array<string, string>
     */
    public static function alternates(?Request $request = null): array
    {
        $request ??= request();
        $base = $request->getSchemeAndHttpHost().$request->getBaseUrl();

        $urls = [];
        foreach ((array) config('cms.locales') as $locale) {
            $urls[$locale] = $base.self::formatPath($request->getPathInfo(), $locale);
        }

        return $urls;
    }
}
