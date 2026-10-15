<?php

namespace Modules\Sections\Support;

/**
 * Small text helpers shared by the section widgets.
 */
final class SectionContent
{
    /**
     * The non-empty, trimmed lines of a text (one word or phrase per line).
     *
     * @return list<string>
     */
    public static function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: []), fn (string $line) => $line !== ''));
    }

    /**
     * Is the link a site path ("/contact", not "//host") or an https:// URL?
     */
    public static function isSafeUrl(string $url): bool
    {
        return preg_match('#^/(?![/\\\\])#', $url) === 1
            || (str_starts_with(strtolower($url), 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false);
    }

    /**
     * The href of a link setting: a site path gets the current language's prefix ("/contact" → "/ru/contact"),
     * an https:// URL is kept, anything else gives null.
     */
    public static function href(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || ! self::isSafeUrl($url)) {
            return null;
        }

        return str_starts_with($url, '/') ? url($url) : $url;
    }
}
