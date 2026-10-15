<?php

namespace Tests;

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleLoader;
use App\Cms\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on a Vite build (public/build/manifest.json).
        $this->withoutVite();
    }

    /**
     * Each request starts in the default locale, as in a real request. Otherwise the locale picked by
     * an earlier request in the test (/ru/…) would prefix the next request's URL (/ru/ky/…).
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     * @param  string|null  $content
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app->setLocale((string) config('cms.default_locale'));

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * Mark a module in modules/ as installed and enabled, and load it (routes, views, translations).
     * Its tables already exist: in tests, every module's migrations run with the app's migrations.
     */
    protected function useModule(string $slug): void
    {
        $modules = $this->app->make(ModuleManager::class);
        $modules->sync();

        $module = Module::where('slug', $slug)->firstOrFail();
        $module->update([
            'installed_at' => now(),
            'installed_version' => $module->version,
            'enabled' => true,
        ]);

        $modules->flush();
        $this->app->make(ModuleLoader::class)->load();
    }

    /**
     * Log in as a member of the super-admin group, who passes every permission check.
     */
    protected function actingAsAdmin(?User $user = null): static
    {
        $user ??= User::factory()->create();
        $user->assignRole(Role::findOrCreate((string) config('cms.super_admin_role'), 'web'));

        return $this->actingAs($user);
    }

    /**
     * Log in as a user whose group has only the given permissions (plus admin.access, to enter the admin).
     *
     * @param  list<string>  $permissions
     */
    protected function actingAsUserWith(array $permissions, ?User $user = null): static
    {
        $user ??= User::factory()->create();
        $role = Role::findOrCreate('test-group-'.md5(implode(',', $permissions)), 'web');

        foreach (['admin.access', ...$permissions] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $user->assignRole($role);

        return $this->actingAs($user);
    }
}
