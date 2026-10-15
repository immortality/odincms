<?php

namespace Modules\Pages\Support;

use Illuminate\Support\Facades\Route;

/**
 * Slugs a top-level page cannot have: the page would be hidden behind another URL. Pages are
 * served by the fallback route, so /blog, /admin, /ru, … always go to the routes that own them.
 */
class ReservedSlugs
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $reserved = [
            trim((string) config('cms.admin_prefix'), '/'),
            ...(array) config('cms.available_locales'),
            ...(array) config('cms.unlocalized_paths'),
        ];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0];

            if (! $route->isFallback && $first !== '' && ! str_starts_with($first, '{')) {
                $reserved[] = $first;
            }
        }

        return array_values(array_unique(array_filter($reserved)));
    }
}
