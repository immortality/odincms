<?php

use App\Cms\Admin\AdminMenu;
use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->modulesPath = storage_path('framework/testing/menu-'.uniqid());
    File::ensureDirectoryExists($this->modulesPath);

    config(['cms.modules_path' => $this->modulesPath]);
    app()->forgetInstance(ModuleManager::class);
    app()->forgetInstance(AdminMenu::class);

    // Admin routes the menus point to.
    foreach (['blog.posts', 'blog.tags', 'pages.pages', 'users.users', 'settings.settings', 'shop.products', 'tours.tours'] as $resource) {
        Route::get('/admin/'.str_replace('.', '/', $resource), fn () => 'ok')->name("admin.{$resource}.index");
        Route::get('/admin/'.str_replace('.', '/', $resource).'/{id}/edit', fn () => 'ok')->name("admin.{$resource}.edit");
    }
    Route::getRoutes()->refreshNameLookups();

    $this->user = User::factory()->create();
});

afterEach(function () {
    File::deleteDirectory($this->modulesPath);
});

/**
 * Add an installed module with the given admin_menu (enabled unless $enabled is false).
 *
 * @param  array<string, mixed>  $adminMenu
 */
function menuModule(string $modulesPath, string $folder, array $adminMenu, bool $enabled = true): void
{
    fakeModule($modulesPath, $folder, ['admin_menu' => $adminMenu]);
    app(ModuleManager::class)->sync();
    Module::where('slug', strtolower($folder))->update(['installed_at' => now(), 'installed_version' => '1.0.0', 'enabled' => $enabled]);
    app(ModuleManager::class)->flush();
}

function item(string $label, string $route, ?string $permission = null): array
{
    return array_filter(['label' => $label, 'route' => $route, 'permission' => $permission]);
}

/**
 * Build the menu as if the current request were for $uri.
 */
function menuFor(?User $user, string $uri = '/admin'): array
{
    $request = Request::create($uri);
    try {
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
    } catch (Throwable) {
        // no matching route (e.g. the dashboard, which is added in Phase 2)
    }

    return app(AdminMenu::class)->build($user, $request);
}

/**
 * @return list<string> "Group: Item" (and "Group: Parent > Child") labels, in order
 */
function menuLabels(array $menu): array
{
    $labels = [];
    foreach ($menu as $group) {
        foreach ($group['items'] as $item) {
            $prefix = ($group['label'] ?? '-').': '.$item['label'];
            $labels[] = $prefix;
            foreach ($item['children'] as $child) {
                $labels[] = $prefix.' > '.$child['label'];
            }
        }
    }

    return $labels;
}

it('starts with the dashboard, then groups and modules in order', function () {
    menuModule($this->modulesPath, 'Settings', ['group' => 'system', 'order' => 1, 'items' => [item('Settings', 'admin.settings.settings.index')]]);
    menuModule($this->modulesPath, 'Pages', ['group' => 'content', 'order' => 20, 'items' => [item('Pages', 'admin.pages.pages.index')]]);
    menuModule($this->modulesPath, 'Blog', ['group' => 'content', 'order' => 10, 'items' => [item('Posts', 'admin.blog.posts.index')]]);
    menuModule($this->modulesPath, 'Users', ['group' => 'users', 'items' => [item('Users', 'admin.users.users.index')]]);

    expect(menuLabels(menuFor($this->user)))->toBe([
        '-: Dashboard',
        'Content: Posts',
        'Content: Pages',
        'Users: Users',
        'System: Settings',
    ]);
});

it('shows one link for a single item and a parent with children for several', function () {
    menuModule($this->modulesPath, 'Blog', [
        'icon' => 'ni-article',
        'label' => 'Blog',
        'items' => [item('Posts', 'admin.blog.posts.index'), item('Tags', 'admin.blog.tags.index')],
    ]);
    menuModule($this->modulesPath, 'Pages', ['icon' => 'ni-file-docs', 'items' => [item('Pages', 'admin.pages.pages.index')]]);

    $content = menuFor($this->user)[1];
    [$blog, $pages] = $content['items'];

    expect($blog['label'])->toBe('Blog')
        ->and($blog['icon'])->toBe('ni-article')
        ->and($blog['url'])->toBe('#')
        ->and(array_column($blog['children'], 'label'))->toBe(['Posts', 'Tags'])
        ->and($blog['children'][0]['url'])->toBe(route('admin.blog.posts.index'))
        ->and($pages['label'])->toBe('Pages')
        ->and($pages['icon'])->toBe('ni-file-docs')
        ->and($pages['url'])->toBe(route('admin.pages.pages.index'))
        ->and($pages['children'])->toBe([]);
});

it('uses the module name for a parent without admin_menu.label', function () {
    menuModule($this->modulesPath, 'Blog', ['items' => [item('Posts', 'admin.blog.posts.index'), item('Tags', 'admin.blog.tags.index')]]);

    expect(menuFor($this->user)[1]['items'][0]['label'])->toBe('Blog');
});

it('hides items the user has no permission for, and parents left without children', function () {
    menuModule($this->modulesPath, 'Blog', ['items' => [
        item('Posts', 'admin.blog.posts.index', 'blog.view'),
        item('Tags', 'admin.blog.tags.index', 'blog.tags'),
    ]]);
    menuModule($this->modulesPath, 'Shop', ['items' => [item('Products', 'admin.shop.products.index', 'shop.view')]]);

    Gate::define('blog.view', fn () => true);

    // Blog keeps one visible item, so it becomes a plain link; Shop disappears.
    expect(menuLabels(menuFor($this->user)))->toBe(['-: Dashboard', 'Content: Posts']);
});

it('skips items whose route does not exist', function () {
    menuModule($this->modulesPath, 'Blog', ['items' => [item('Posts', 'admin.blog.posts.index'), item('Typo', 'admin.blog.typo.index')]]);

    expect(menuLabels(menuFor($this->user)))->toBe(['-: Dashboard', 'Content: Posts']);
});

it('leaves out disabled modules and modules without admin_menu', function () {
    menuModule($this->modulesPath, 'Blog', ['items' => [item('Posts', 'admin.blog.posts.index')]], enabled: false);
    menuModule($this->modulesPath, 'Pages', []);

    expect(menuLabels(menuFor($this->user)))->toBe(['-: Dashboard']);
});

it('puts modules without a group in the default group, and labels unknown groups', function () {
    menuModule($this->modulesPath, 'Blog', ['items' => [item('Posts', 'admin.blog.posts.index')]]);
    menuModule($this->modulesPath, 'Tours', ['group' => 'travel-agency', 'items' => [item('Tours', 'admin.tours.tours.index')]]);

    expect(menuLabels(menuFor($this->user)))->toBe(['-: Dashboard', 'Content: Posts', 'Travel Agency: Tours']);
});

it('marks the item of the current resource and its parent as active', function () {
    menuModule($this->modulesPath, 'Blog', ['items' => [item('Posts', 'admin.blog.posts.index'), item('Tags', 'admin.blog.tags.index')]]);
    menuModule($this->modulesPath, 'Pages', ['items' => [item('Pages', 'admin.pages.pages.index')]]);

    $menu = menuFor($this->user, '/admin/blog/posts/5/edit');
    [$blog, $pages] = $menu[1]['items'];

    expect($menu[0]['items'][0]['active'])->toBeFalse()
        ->and($blog['active'])->toBeTrue()
        ->and($blog['children'][0]['active'])->toBeTrue()
        ->and($blog['children'][1]['active'])->toBeFalse()
        ->and($pages['active'])->toBeFalse();
});

it('marks the dashboard as active on the dashboard', function () {
    expect(menuFor($this->user, '/admin')[0]['items'][0]['active'])->toBeTrue();
});

it('translates the labels', function () {
    Lang::addLines(['admin.posts' => 'Записи'], 'ru', 'blog');
    app()->setLocale('ru');
    menuModule($this->modulesPath, 'Blog', ['items' => [item('blog::admin.posts', 'admin.blog.posts.index')]]);

    expect(menuFor($this->user)[1]['items'][0]['label'])->toBe('Записи');
});

it('is cached until a module lifecycle action flushes it', function () {
    menuModule($this->modulesPath, 'Blog', ['items' => [item('Posts', 'admin.blog.posts.index', 'blog.view')]]);
    expect(menuLabels(menuFor($this->user)))->toBe(['-: Dashboard']);

    Gate::define('blog.view', fn () => true);
    expect(menuLabels(menuFor($this->user)))->toBe(['-: Dashboard']);

    app(ModuleManager::class)->flush();
    expect(menuLabels(menuFor($this->user)))->toBe(['-: Dashboard', 'Content: Posts']);
});

it('caches the menu per role set', function () {
    menuModule($this->modulesPath, 'Blog', ['items' => [item('Posts', 'admin.blog.posts.index', 'blog.view')]]);
    Permission::create(['name' => 'blog.view']);
    Role::create(['name' => 'editor'])->givePermissionTo('blog.view');

    $editor = User::factory()->create()->assignRole('editor');
    $otherEditor = User::factory()->create()->assignRole('editor');

    expect(menuLabels(menuFor($this->user)))->toBe(['-: Dashboard'])
        ->and(menuLabels(menuFor($editor)))->toBe(['-: Dashboard', 'Content: Posts'])
        ->and(menuLabels(menuFor($otherEditor)))->toBe(['-: Dashboard', 'Content: Posts']);
});

it('gives users with direct permissions their own menu', function () {
    menuModule($this->modulesPath, 'Blog', ['items' => [item('Posts', 'admin.blog.posts.index', 'blog.view')]]);
    Permission::create(['name' => 'blog.view']);

    // Neither user has a role, but one was given the permission directly.
    $direct = User::factory()->create()->givePermissionTo('blog.view');

    expect(menuLabels(menuFor($this->user)))->toBe(['-: Dashboard'])
        ->and(menuLabels(menuFor($direct)))->toBe(['-: Dashboard', 'Content: Posts']);
});

it('feeds the admin sidebar', function () {
    menuModule($this->modulesPath, 'Blog', ['icon' => 'ni-article', 'items' => [item('Posts', 'admin.blog.posts.index')]]);

    $this->actingAsAdmin($this->user)->get('/admin')
        ->assertOk()
        ->assertSee('Content')
        ->assertSee('fas fa-newspaper', false)
        ->assertSee(route('admin.blog.posts.index'), false);
});
