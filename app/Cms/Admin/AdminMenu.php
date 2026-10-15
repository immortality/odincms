<?php

namespace App\Cms\Admin;

use App\Cms\Modules\Manifest;
use App\Cms\Modules\ModuleManager;
use App\Cms\Support\CmsCache;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Builds the admin sidebar from the "admin_menu" of the enabled modules' manifests (spec §7).
 *
 * The filtered structure (translated labels, route names) is cached per role set and locale in
 * CmsCache, so module lifecycle actions clear it. URLs and the active state are added per request.
 *
 * Result, as used by resources/views/admin/partials/sidebar.blade.php:
 * [ ['label' => 'Content', 'items' => [ ['label', 'url', 'icon', 'active', 'children' => [ ['label', 'url', 'active'] ]] ]] ]
 */
class AdminMenu
{
    public function __construct(
        private ModuleManager $modules,
        private CmsCache $cache,
    ) {}

    /**
     * The menu for $user, with URLs and active flags for the current request.
     *
     * @return list<array{label: string|null, items: list<array<string, mixed>>}>
     */
    public function build(?Authenticatable $user, ?Request $request = null): array
    {
        $request ??= request();
        $currentRoute = (string) $request->route()?->getName();

        $groups = $this->cache->rememberForever(
            'admin.menu.'.app()->getLocale().'.'.$this->roleSetKey($user),
            fn () => $this->structure($user),
        );

        $prefix = trim((string) config('cms.admin_prefix'), '/');

        // The dashboard is always first.
        array_unshift($groups, ['label' => null, 'items' => [[
            'label' => __('Dashboard'),
            'url' => url($prefix),
            'route' => null,
            'icon' => 'fas fa-th-large',
            'active' => $request->is($prefix),
            'children' => [],
        ]]]);

        return array_map(fn (array $group) => [
            'label' => $group['label'],
            'items' => array_map(fn (array $item) => $this->resolve($item, $currentRoute), $group['items']),
        ], $groups);
    }

    /**
     * Groups and items the user may see, without URLs (cacheable).
     *
     * @return list<array{label: string|null, order: int, items: list<array<string, mixed>>}>
     */
    private function structure(?Authenticatable $user): array
    {
        $configGroups = (array) config('cms.admin_menu_groups', []);
        $defaultGroup = (string) config('cms.admin_menu_default_group', 'content');
        $groups = [];

        foreach ($this->modules->enabledManifests() as $manifest) {
            $item = $this->moduleItem($manifest, $user);
            if ($item === null) {
                continue;
            }

            $menu = $manifest->adminMenu();
            $key = is_string($menu['group'] ?? null) ? $menu['group'] : $defaultGroup;

            $groups[$key] ??= [
                'label' => __((string) ($configGroups[$key]['label'] ?? Str::headline($key))),
                'order' => (int) ($configGroups[$key]['order'] ?? 50),
                'items' => [],
            ];
            $groups[$key]['items'][] = $item;
        }

        foreach ($groups as &$group) {
            usort($group['items'], fn (array $a, array $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);
        }
        unset($group);

        $groups = array_values($groups);
        usort($groups, fn (array $a, array $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);

        return $groups;
    }

    /**
     * One sidebar entry for a module: a link when it has one visible item, a parent with
     * children when it has several, null when the user can see none.
     *
     * @return array<string, mixed>|null
     */
    private function moduleItem(Manifest $manifest, ?Authenticatable $user): ?array
    {
        $menu = $manifest->adminMenu();
        $children = [];

        /** @var list<array{label: string, route: string, permission?: string}> $items */
        $items = $menu['items'] ?? [];

        foreach ($items as $item) {
            if (! Route::has($item['route'])) {
                continue;
            }
            if (isset($item['permission']) && ! $this->can($user, $item['permission'])) {
                continue;
            }

            $children[] = ['label' => __($item['label']), 'route' => $item['route']];
        }

        if ($children === []) {
            return null;
        }

        $base = [
            'icon' => is_string($menu['icon'] ?? null) ? $menu['icon'] : 'fas fa-cubes',
            'order' => is_int($menu['order'] ?? null) ? $menu['order'] : 100,
        ];

        if (count($children) === 1) {
            return [...$base, ...$children[0], 'children' => []];
        }

        return [
            ...$base,
            'label' => __(is_string($menu['label'] ?? null) ? $menu['label'] : $manifest->name),
            'route' => null,
            'children' => $children,
        ];
    }

    /**
     * Add the URL and active flag to a cached item (and its children).
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function resolve(array $item, string $currentRoute): array
    {
        /** @var list<array{label: string, route: string}> $children */
        $children = $item['children'] ?? [];

        $resolvedChildren = array_map(fn (array $child) => [
            'label' => $child['label'],
            'url' => route($child['route']),
            'active' => $this->isActive($child['route'], $currentRoute),
        ], $children);

        $route = is_string($item['route'] ?? null) ? $item['route'] : null;

        return [
            'label' => $item['label'],
            'url' => $item['url'] ?? ($route ? route($route) : '#'),
            'icon' => $item['icon'] ?? null,
            'active' => (bool) ($item['active'] ?? false)
                || ($route !== null && $this->isActive($route, $currentRoute))
                || in_array(true, array_column($resolvedChildren, 'active'), true),
            'children' => $resolvedChildren,
        ];
    }

    /**
     * "admin.blog.posts.index" is active on every "admin.blog.posts.*" route (create, edit, ...).
     */
    private function isActive(string $route, string $currentRoute): bool
    {
        if ($currentRoute === '') {
            return false;
        }

        $resource = Str::contains($route, '.') ? Str::beforeLast($route, '.') : $route;

        return $currentRoute === $route || Str::startsWith($currentRoute, $resource.'.');
    }

    private function can(?Authenticatable $user, string $permission): bool
    {
        return $user instanceof Authorizable && $user->can($permission);
    }

    /**
     * Users with the same roles see the same menu (spec §7). Users with direct permissions, and
     * user models without roles, get a personal key, so permission checks are never shared wrongly.
     */
    private function roleSetKey(?Authenticatable $user): string
    {
        if ($user === null) {
            return 'guest';
        }

        if (method_exists($user, 'getRoleNames')) {
            // Permissions given to the user directly (not through a group) make the menu personal.
            if (method_exists($user, 'getDirectPermissions') && $user->getDirectPermissions()->isNotEmpty()) {
                return 'user-'.$user->getAuthIdentifier();
            }

            /** @var Collection<int, string> $roles */
            $roles = $user->getRoleNames();

            return 'roles-'.md5($roles->sort()->implode(','));
        }

        return 'user-'.$user->getAuthIdentifier();
    }
}
