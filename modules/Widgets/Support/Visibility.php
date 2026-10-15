<?php

namespace Modules\Widgets\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A widget instance's visibility rules (spec §9):
 *
 *   pages:    mode "all" | "only" | "except", with patterns that match a route name ("blog.*")
 *             or a path ("tours/*"; "/" is the home page)
 *   locales:  [] = all, or the listed locales
 *   audience: "everyone" | "guests" | "members" | "groups" (with "groups": [group names])
 */
class Visibility
{
    public const AUDIENCES = ['everyone', 'guests', 'members', 'groups'];

    public const PAGE_MODES = ['all', 'only', 'except'];

    /**
     * @param  array<string, mixed>|null  $rules
     */
    public static function allows(?array $rules, Request $request, ?Authenticatable $user, string $locale): bool
    {
        $rules ??= [];

        return self::pages((array) ($rules['pages'] ?? []), $request)
            && self::locales((array) ($rules['locales'] ?? []), $locale)
            && self::audience((string) ($rules['audience'] ?? 'everyone'), (array) ($rules['groups'] ?? []), $user);
    }

    /**
     * @param  array<string, mixed>  $pages
     */
    private static function pages(array $pages, Request $request): bool
    {
        $mode = $pages['mode'] ?? 'all';
        $patterns = array_values(array_filter((array) ($pages['patterns'] ?? []), 'is_string'));

        if ($mode === 'all' || $patterns === []) {
            return true; // the form requires patterns for "only" and "except"
        }

        $route = (string) $request->route()?->getName();
        $path = trim($request->path(), '/');
        $matches = false;

        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if (($route !== '' && Str::is($pattern, $route)) || Str::is(trim($pattern, '/'), $path)) {
                $matches = true;
                break;
            }
        }

        return $mode === 'only' ? $matches : ! $matches;
    }

    /**
     * @param  array<int, mixed>  $locales
     */
    private static function locales(array $locales, string $locale): bool
    {
        return $locales === [] || in_array($locale, $locales, true);
    }

    /**
     * @param  array<int, mixed>  $groups
     */
    private static function audience(string $audience, array $groups, ?Authenticatable $user): bool
    {
        return match ($audience) {
            'guests' => $user === null,
            'members' => $user !== null,
            'groups' => $user instanceof User && $groups !== [] && $user->hasAnyRole(array_values(array_filter($groups, 'is_string'))),
            default => true,
        };
    }
}
