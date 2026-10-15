<?php

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleLoader;
use App\Cms\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\BootSelf\Providers\BootSelfServiceProvider;

beforeEach(function () {
    $this->modulesPath = storage_path('framework/testing/boot-'.uniqid());
    File::ensureDirectoryExists($this->modulesPath);

    config(['cms.modules_path' => $this->modulesPath]);
    app()->forgetInstance(ModuleManager::class);
    app()->forgetInstance(ModuleLoader::class);
});

afterEach(function () {
    File::deleteDirectory($this->modulesPath);
});

/**
 * Sync the modules folder, give the module the requested state, and run the boot sequence.
 */
function bootWith(string $slug, string $state): void
{
    app(ModuleManager::class)->sync();

    $module = Module::where('slug', $slug)->sole();
    match ($state) {
        'enabled' => $module->update(['installed_at' => now(), 'installed_version' => $module->version, 'enabled' => true]),
        'disabled' => $module->update(['installed_at' => now(), 'installed_version' => $module->version, 'enabled' => false]),
        'not installed' => null,
    };

    app(ModuleManager::class)->flush();
    app(ModuleLoader::class)->load();
}

describe('an enabled module', function () {
    it('registers its public routes, views and translations', function () {
        $slug = bootableModule($this->modulesPath, 'BootPublic');
        bootWith($slug, 'enabled');

        expect(app(ModuleLoader::class)->loaded())->toBe(['boot-public']);

        $this->get('/boot-public-hello')
            ->assertOk()
            ->assertSee('Hello from BootPublic module');
    });

    it('registers its admin routes under the admin prefix with the admin. name prefix', function () {
        $slug = bootableModule($this->modulesPath, 'BootAdmin');
        bootWith($slug, 'enabled');

        expect(Route::has('admin.boot-admin.index'))->toBeTrue()
            ->and(route('admin.boot-admin.index', absolute: false))->toBe('/admin/boot-admin');
    });

    it('protects its admin routes with auth and the admin middleware', function () {
        $slug = bootableModule($this->modulesPath, 'BootGuard');
        bootWith($slug, 'enabled');

        // Guests are sent to the login page.
        $this->get('/admin/boot-guard')->assertRedirect(route('login'));

        // Logged in, but without admin.access.
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/boot-guard')->assertForbidden();

        // With admin.access.
        Gate::define('admin.access', fn () => true);
        $this->actingAs($user)->get('/admin/boot-guard')->assertOk()->assertSee('admin of boot-guard');
    });

    it('uses the configured admin prefix', function () {
        config(['cms.admin_prefix' => 'backend']);
        $slug = bootableModule($this->modulesPath, 'BootPrefix');
        bootWith($slug, 'enabled');

        expect(route('admin.boot-prefix.index', absolute: false))->toBe('/backend/boot-prefix');
    });
});

describe('a module that is not booted', function () {
    it('returns 404 for the routes of a disabled module', function () {
        $slug = bootableModule($this->modulesPath, 'BootDisabled');
        bootWith($slug, 'disabled');

        expect(app(ModuleLoader::class)->loaded())->toBe([])
            ->and(Route::has('admin.boot-disabled.index'))->toBeFalse();

        $this->get('/boot-disabled-hello')->assertNotFound();
        $this->get('/admin/boot-disabled')->assertNotFound();
    });

    it('returns 404 for the routes of a module that is not installed', function () {
        $slug = bootableModule($this->modulesPath, 'BootNotInstalled');
        bootWith($slug, 'not installed');

        $this->get('/boot-not-installed-hello')->assertNotFound();
    });
});

describe('boot guards', function () {
    it('boots without modules before the modules table exists', function () {
        Schema::drop('modules');

        app(ModuleLoader::class)->load();

        expect(app(ModuleLoader::class)->loaded())->toBe([]);
    });

    it('skips an enabled module whose manifest has become invalid, and logs it', function () {
        $slug = bootableModule($this->modulesPath, 'BootBroken');
        app(ModuleManager::class)->sync();
        Module::where('slug', $slug)->update(['installed_at' => now(), 'installed_version' => '1.0.0', 'enabled' => true]);
        app(ModuleManager::class)->flush();
        app(ModuleManager::class)->enabledManifests(); // caches the manifest path

        File::put($this->modulesPath.'/BootBroken/module.json', '{ broken');
        Log::spy();

        app(ModuleLoader::class)->load();

        expect(app(ModuleLoader::class)->loaded())->toBe([]);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'module "boot-broken" not loaded'));
    });

    it('does not load a module twice', function () {
        $slug = bootableModule($this->modulesPath, 'BootTwice');
        bootWith($slug, 'enabled');

        app(ModuleLoader::class)->load();

        expect(app(ModuleLoader::class)->loaded())->toBe(['boot-twice']);
    });
});

it('lets a module provider find its own manifest when none was given', function () {
    bootableModule($this->modulesPath, 'BootSelf');

    $provider = new BootSelfServiceProvider(app());

    expect($provider->manifest()->slug)->toBe('boot-self');
});
