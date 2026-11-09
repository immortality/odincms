<?php

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Blog\Models\Post;
use Modules\Categories\Models\Category;
use Modules\Groups\Database\Seeders\GroupsDatabaseSeeder;
use Modules\Groups\Models\Group;
use Modules\Media\Models\MediaFile;
use Modules\Media\Models\MediaFolder;
use Modules\Navigation\Models\NavigationGroup;
use Modules\Pages\Models\Page;
use Modules\Services\Models\Service;
use Modules\Widgets\Models\WidgetArea;

/**
 * Step 8.2 / spec §15: every admin route is protected by a permission. A user who may enter the
 * admin (admin.access) but has no other permission gets 403 everywhere except these routes.
 */
const OPEN_ADMIN_ROUTES = [
    'admin.dashboard',
    'admin.users.profile',          // everyone manages their own profile
    'admin.users.profile.update',
    'admin.users.profile.password',
];

beforeEach(function () {
    foreach (['groups', 'users', 'permissions', 'modules', 'settings', 'navigation', 'widgets', 'categories', 'blog', 'pages', 'services', 'media'] as $module) {
        $this->useModule($module);
    }
    $this->seed(GroupsDatabaseSeeder::class);
});

/**
 * A real value for each route parameter, so requests reach the authorization checks.
 *
 * @return array<string, mixed>
 */
function adminRouteParameters(string $name): array
{
    static $values = null;

    $values ??= (function () {
        $menu = NavigationGroup::create(['slug' => 'sweep', 'title' => ['en' => 'Sweep']]);
        $area = WidgetArea::create(['slug' => 'sweep', 'name' => ['en' => 'Sweep'], 'theme' => 'default']);

        return [
            'post' => Post::factory()->create()->id,
            'category' => Category::factory()->create(['type' => 'blog'])->id,
            'type' => 'blog',
            'page' => Page::factory()->create()->id,
            'user' => User::factory()->create()->id,
            'role_group' => Group::findByName('user')->id,
            'menu_group' => $menu->slug, // menus are addressed by slug
            'link' => $menu->links()->create(['type' => 'url', 'url' => '/x', 'title' => ['en' => 'X'], 'sort_order' => 1])->id,
            'area' => $area->id,
            'instance' => $area->instances()->create(['widget' => 'core.html', 'settings' => [], 'sort_order' => 1, 'is_active' => true])->id,
            'module' => 'blog',
            'action' => 'disable',
            'section' => 'site',
            'item' => Service::factory()->create()->id, // generated modules name the record {item}
            'folder' => MediaFolder::create(['name' => 'Sweep'])->id,
            'file' => MediaFile::create(['name' => 'sweep.pdf', 'disk' => 'public', 'path' => 'media/sweep.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'size' => 1])->id,
        ];
    })();

    $parameters = $values;
    // "group" is a navigation menu under admin.navigation, a user group under admin.groups.
    $parameters['group'] = str_starts_with($name, 'admin.navigation.') ? $values['menu_group'] : $values['role_group'];

    return $parameters;
}

it('forbids every admin route to a user without its permission', function () {
    $user = User::factory()->create();
    $this->actingAsUserWith([], $user);

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => Str::startsWith((string) $route->getName(), 'admin.'))
        ->reject(fn (RoutingRoute $route) => in_array($route->getName(), OPEN_ADMIN_ROUTES, true));

    expect($routes->count())->toBeGreaterThan(60);

    $open = [];
    foreach ($routes as $route) {
        $name = (string) $route->getName();
        $parameters = array_intersect_key(adminRouteParameters($name), array_flip($route->parameterNames()));
        $method = collect($route->methods())->reject(fn ($method) => $method === 'HEAD')->first();

        $status = $this->actingAs($user)->call($method, route($name, $parameters))->getStatusCode();

        if ($status !== 403) {
            $open[] = "{$method} {$name} → {$status}";
        }
    }

    expect($open)->toBe([]);
});

it('lets that user open the dashboard and their own profile', function () {
    $this->actingAsUserWith([])->get(route('admin.dashboard'))->assertOk();
    $this->actingAsUserWith([])->get(route('admin.users.profile'))->assertOk();
});
