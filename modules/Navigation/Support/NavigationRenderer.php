<?php

namespace Modules\Navigation\Support;

use App\Cms\Support\CmsCache;
use App\Cms\Support\Linkable;
use App\Cms\Support\LinkableRegistry;
use App\Cms\Support\LocalizedUrls;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Modules\Navigation\Models\NavigationGroup;
use Modules\Navigation\Models\NavigationLink;
use Throwable;

/**
 * Builds a menu for the navigation('header') Blade directive (spec §10).
 *
 * The tree (labels, URLs) is cached per group, locale and role set in CmsCache; saving links,
 * groups or any registered Linkable model clears it. The active item is marked per request.
 *
 * Item: ['label', 'url', 'new_tab', 'css_class', 'active', 'children' => [...]]
 */
class NavigationRenderer
{
    public function __construct(
        private CmsCache $cache,
        private LinkableRegistry $linkables,
    ) {}

    /**
     * Render a menu with the default Bootstrap view or $view (which receives $items and $slug).
     */
    public function render(string $slug, ?string $view = null): HtmlString
    {
        return new HtmlString(view($view ?? 'navigation::default', [
            'items' => $this->items($slug),
            'slug' => $slug,
        ])->render());
    }

    /**
     * The menu's items for the current user and request, with active flags.
     *
     * @return list<array<string, mixed>>
     */
    public function items(string $slug, ?Authenticatable $user = null, ?Request $request = null): array
    {
        $user ??= auth()->user();
        $request ??= request();

        $tree = $this->cache->rememberForever(
            'navigation.'.$slug.'.'.app()->getLocale().'.'.$this->roleSetKey($user),
            fn () => $this->tree($slug, $user),
        );

        return $this->markActive($tree, $request);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tree(string $slug, ?Authenticatable $user): array
    {
        $group = NavigationGroup::where('slug', $slug)->first();
        if ($group === null) {
            return [];
        }

        $byParent = [];
        foreach (NavigationLink::where('group_id', $group->id)->where('is_active', true)->with('linkable')
            ->orderBy('sort_order')->orderBy('id')->get() as $link) {
            $byParent[$link->parent_id ?? 0][] = $link;
        }

        return $this->branch($byParent, 0, $user);
    }

    /**
     * A hidden, inactive or broken link hides its children too.
     *
     * @param  array<int, list<NavigationLink>>  $byParent  links by parent id (0 = top level)
     * @return list<array<string, mixed>>
     */
    private function branch(array $byParent, int $parentId, ?Authenticatable $user): array
    {
        $items = [];

        foreach ($byParent[$parentId] ?? [] as $link) {
            if (! $this->visibleTo($link, $user) || ($url = $this->url($link)) === null) {
                continue;
            }

            $items[] = [
                'label' => $this->label($link),
                'url' => $url,
                'new_tab' => $link->open_new_tab,
                'css_class' => $link->css_class,
                'active' => false,
                'children' => $this->branch($byParent, $link->id, $user),
            ];
        }

        return $items;
    }

    public function url(NavigationLink $link): ?string
    {
        return match ($link->type) {
            'url' => filled($link->url) ? (string) $link->url : null,
            // An address on this site: url() adds the visitor's language prefix (/ru/contact).
            'path' => $link->url !== null ? url('/'.ltrim($link->url, '/')) : null,
            'route' => $this->routeUrl($link),
            'model' => $this->modelUrl($link),
            default => null,
        };
    }

    private function routeUrl(NavigationLink $link): ?string
    {
        if (! $link->route_name || ! Route::has($link->route_name)) {
            return null;
        }

        try {
            return route($link->route_name, $link->route_parameters ?? []);
        } catch (Throwable) {
            return null; // parameters no longer fit the route
        }
    }

    private function modelUrl(NavigationLink $link): ?string
    {
        // A model whose module is disabled is not registered any more.
        if (! $link->linkable_type || ! $this->linkables->has($link->linkable_type)) {
            return null;
        }

        $model = $link->linkable;

        return $model instanceof Linkable ? $model->linkUrl() : null;
    }

    private function label(NavigationLink $link): string
    {
        $title = $link->getTranslation('title', app()->getLocale(), true);

        if (filled($title)) {
            return (string) $title;
        }

        return $link->type === 'model' && $link->linkable instanceof Linkable
            ? $link->linkable->linkLabel()
            : (string) $this->url($link);
    }

    public function visibleTo(NavigationLink $link, ?Authenticatable $user): bool
    {
        $rules = $link->visibility ?? [];
        if ($rules === []) {
            return true;
        }

        if ($user === null) {
            return in_array(NavigationLink::GUESTS, $rules, true);
        }

        return in_array(NavigationLink::MEMBERS, $rules, true)
            || ($user instanceof User && $user->hasAnyRole(array_values(array_diff($rules, [NavigationLink::GUESTS, NavigationLink::MEMBERS]))));
    }

    /**
     * An item is active when it points to the current page (same path on this site); its parents are active too.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function markActive(array $items, Request $request): array
    {
        foreach ($items as &$item) {
            /** @var list<array<string, mixed>> $children */
            $children = $item['children'];
            $item['children'] = $this->markActive($children, $request);
            $item['active'] = $this->isCurrentPage((string) $item['url'], $request)
                || in_array(true, array_column($item['children'], 'active'), true);
        }

        return $items;
    }

    /**
     * Compared by path, so http/https and the port don't matter; links to other sites are never current.
     */
    private function isCurrentPage(string $url, Request $request): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $siteHosts = [$request->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)];

        if (is_string($host) && ! in_array($host, $siteHosts, true)) {
            return false;
        }

        // "http://site" has no path: it is the home page. A bare "#anchor" or "?query" is never current.
        $path = parse_url($url, PHP_URL_PATH) ?? (is_string($host) ? '/' : null);

        // The current URL as generated, i.e. with the locale prefix (/ru/blog), like the links.
        $current = $request->getBaseUrl().LocalizedUrls::formatPath($request->getPathInfo());

        return is_string($path) && trim($path, '/') === trim($current, '/');
    }

    /**
     * Visibility depends only on being a guest and on the user's groups.
     */
    private function roleSetKey(?Authenticatable $user): string
    {
        if ($user === null) {
            return 'guest';
        }

        return 'roles-'.md5($user instanceof User ? $user->getRoleNames()->sort()->implode(',') : '');
    }
}
