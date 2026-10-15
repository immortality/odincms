<?php

use App\Cms\Modules\Contracts\PermissionSynchronizer;
use App\Cms\Modules\Contracts\WidgetInstanceCleaner;
use App\Cms\Modules\Events\ModuleDisabled;
use App\Cms\Modules\Events\ModuleEnabled;
use App\Cms\Modules\Events\ModuleInstalled;
use App\Cms\Modules\Events\ModuleUninstalled;
use App\Cms\Modules\Events\ModuleUpgraded;
use App\Cms\Modules\Exceptions\ModuleException;
use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Modules\RecordingPermissionSynchronizer;
use Tests\Fixtures\Modules\RecordingWidgetInstanceCleaner;

beforeEach(function () {
    $this->modulesPath = storage_path('framework/testing/lifecycle-'.uniqid());
    File::ensureDirectoryExists($this->modulesPath);

    config([
        'cms.modules_path' => $this->modulesPath,
        'cms.core_modules' => ['users', 'categories'],
        'cms.disableable_core_modules' => ['categories'],
    ]);

    $this->permissions = new RecordingPermissionSynchronizer;
    $this->widgets = new RecordingWidgetInstanceCleaner;
    app()->instance(PermissionSynchronizer::class, $this->permissions);
    app()->instance(WidgetInstanceCleaner::class, $this->widgets);
    app()->forgetInstance(ModuleManager::class);

    $this->modules = app(ModuleManager::class);

    Event::fake([ModuleInstalled::class, ModuleEnabled::class, ModuleDisabled::class, ModuleUninstalled::class, ModuleUpgraded::class]);
});

afterEach(function () {
    File::deleteDirectory($this->modulesPath);
});

/**
 * Create a module folder (optionally with one migration creating $table) and sync it.
 *
 * @param  array<string, mixed>  $manifest
 */
function lifecycleModule(string $modulesPath, string $folder, array $manifest = [], ?string $table = null): void
{
    fakeModule($modulesPath, $folder, $manifest);

    if ($table) {
        fakeModuleMigration($modulesPath, $folder, "2026_01_01_000000_create_{$table}_table", $table);
    }

    app(ModuleManager::class)->sync();
}

function module(string $slug): Module
{
    return Module::where('slug', $slug)->sole();
}

describe('install', function () {
    it('runs the migrations, syncs permissions and enables the module', function () {
        lifecycleModule($this->modulesPath, 'Blog', ['version' => '1.2.0'], 'lc_blog_posts');

        $this->modules->install('blog');

        $blog = module('blog');
        expect(Schema::hasTable('lc_blog_posts'))->toBeTrue()
            ->and($blog->isInstalled())->toBeTrue()
            ->and($blog->installed_version)->toBe('1.2.0')
            ->and($blog->enabled)->toBeTrue()
            ->and($this->modules->isEnabled('blog'))->toBeTrue()
            ->and($this->permissions->synced)->toBe(['blog']);

        Event::assertDispatched(ModuleInstalled::class, fn ($event) => $event->module->slug === 'blog');
    });

    it('runs the module seeder after the migrations', function () {
        lifecycleModule($this->modulesPath, 'Seeded', [], 'lc_seeded_items');
        fakeModuleSeeder($this->modulesPath, 'Seeded', 'lc_seeded_items');

        $this->modules->install('seeded');

        expect(DB::table('lc_seeded_items')->pluck('name')->all())->toBe(['seeded']);
    });

    it('rolls back the migrations when the seeder fails', function () {
        lifecycleModule($this->modulesPath, 'BrokenSeed', [], 'lc_broken_items');
        fakeModuleSeeder($this->modulesPath, 'BrokenSeed', 'lc_broken_items', fail: true);

        expect(fn () => $this->modules->install('broken-seed'))->toThrow(RuntimeException::class, 'Seeder failed');

        expect(Schema::hasTable('lc_broken_items'))->toBeFalse()
            ->and(module('broken-seed')->isInstalled())->toBeFalse()
            ->and($this->permissions->synced)->toBe([]);
    });

    it('works for a module without migrations', function () {
        lifecycleModule($this->modulesPath, 'Plain');

        expect($this->modules->install('plain')->isActive())->toBeTrue();
    });

    it('fails for an unknown module', function () {
        $this->modules->install('nope');
    })->throws(ModuleException::class, 'not found');

    it('fails when already installed', function () {
        lifecycleModule($this->modulesPath, 'Blog');
        $this->modules->install('blog');

        $this->modules->install('blog');
    })->throws(ModuleException::class, 'already installed');

    it('fails when the folder is missing', function () {
        Module::factory()->create(['slug' => 'shop']);

        $this->modules->install('shop');
    })->throws(ModuleException::class, 'folder of module "shop" is missing');

    it('requires its dependencies to be installed and enabled', function () {
        lifecycleModule($this->modulesPath, 'Categories');
        lifecycleModule($this->modulesPath, 'Blog', ['requires' => ['categories']], 'lc_blog_posts');

        expect(fn () => $this->modules->install('blog'))
            ->toThrow(ModuleException::class, 'requires "categories" to be installed and enabled first');
        expect(Schema::hasTable('lc_blog_posts'))->toBeFalse();

        $this->modules->install('categories');
        $this->modules->disable('categories');
        expect(fn () => $this->modules->install('blog'))->toThrow(ModuleException::class, 'requires "categories"');

        $this->modules->enable('categories');
        expect($this->modules->install('blog')->isActive())->toBeTrue();
    });
});

describe('enable and disable', function () {
    it('disables and enables a module, keeping its data', function () {
        lifecycleModule($this->modulesPath, 'Blog', [], 'lc_blog_posts');
        $this->modules->install('blog');
        DB::table('lc_blog_posts')->insert(['name' => 'Hello']);

        $this->modules->disable('blog');
        expect(module('blog')->enabled)->toBeFalse()
            ->and($this->modules->isEnabled('blog'))->toBeFalse()
            ->and(DB::table('lc_blog_posts')->count())->toBe(1);
        Event::assertDispatched(ModuleDisabled::class);

        $this->modules->enable('blog');
        expect($this->modules->isEnabled('blog'))->toBeTrue();
        Event::assertDispatched(ModuleEnabled::class);
    });

    it('does nothing when the module already has the requested state', function () {
        lifecycleModule($this->modulesPath, 'Blog');
        $this->modules->install('blog');

        $this->modules->enable('blog');
        Event::assertNotDispatched(ModuleEnabled::class);

        $this->modules->disable('blog');
        $this->modules->disable('blog');
        Event::assertDispatchedTimes(ModuleDisabled::class, 1);
    });

    it('cannot enable or disable a module that is not installed', function (string $action) {
        lifecycleModule($this->modulesPath, 'Blog');

        $this->modules->{$action}('blog');
    })->with(['enable', 'disable'])->throws(ModuleException::class, 'not installed');

    it('cannot enable a module while a dependency is disabled', function () {
        lifecycleModule($this->modulesPath, 'Categories');
        lifecycleModule($this->modulesPath, 'Blog', ['requires' => ['categories']]);
        $this->modules->install('categories');
        $this->modules->install('blog');
        $this->modules->disable('blog');
        $this->modules->disable('categories');

        $this->modules->enable('blog');
    })->throws(ModuleException::class, 'requires "categories"');

    it('cannot disable a module that an enabled module depends on', function () {
        lifecycleModule($this->modulesPath, 'Categories');
        lifecycleModule($this->modulesPath, 'Blog', ['requires' => ['categories']]);
        $this->modules->install('categories');
        $this->modules->install('blog');

        expect(fn () => $this->modules->disable('categories'))
            ->toThrow(ModuleException::class, 'cannot be disabled: "blog" depends on it');

        // Once the dependent is disabled, the dependency can be disabled too.
        $this->modules->disable('blog');
        expect($this->modules->disable('categories')->enabled)->toBeFalse();
    });

    it('protects core modules from being disabled, except the disableable ones', function () {
        lifecycleModule($this->modulesPath, 'Users');
        lifecycleModule($this->modulesPath, 'Categories');
        $this->modules->install('users');
        $this->modules->install('categories');

        expect(fn () => $this->modules->disable('users'))->toThrow(ModuleException::class, 'core module and cannot be disabled')
            ->and($this->modules->disable('categories')->enabled)->toBeFalse();
    });
});

describe('uninstall', function () {
    it('rolls back only its own migrations and resets the module to not installed', function () {
        lifecycleModule($this->modulesPath, 'Blog', [], 'lc_blog_posts');
        lifecycleModule($this->modulesPath, 'Shop', [], 'lc_shop_products');
        $this->modules->install('blog');
        $this->modules->install('shop'); // a later migration batch than blog

        $this->modules->uninstall('blog');

        $blog = module('blog');
        expect(Schema::hasTable('lc_blog_posts'))->toBeFalse()
            ->and(Schema::hasTable('lc_shop_products'))->toBeTrue()
            ->and($blog->isInstalled())->toBeFalse()
            ->and($blog->installed_version)->toBeNull()
            ->and($blog->enabled)->toBeFalse()
            ->and($this->modules->isEnabled('blog'))->toBeFalse()
            ->and($this->permissions->removed)->toBe(['blog'])
            ->and($this->widgets->removed)->toBe(['blog']);
        Event::assertDispatched(ModuleUninstalled::class);

        // It can be installed again.
        expect($this->modules->install('blog')->isActive())->toBeTrue()
            ->and(Schema::hasTable('lc_blog_posts'))->toBeTrue();
    });

    it('protects every core module, including disableable ones', function (string $folder) {
        lifecycleModule($this->modulesPath, $folder);
        $this->modules->install(strtolower($folder));

        $this->modules->uninstall(strtolower($folder));
    })->with(['Users', 'Categories'])->throws(ModuleException::class, 'core module and cannot be uninstalled');

    it('cannot uninstall a module that an installed module depends on, even a disabled one', function () {
        lifecycleModule($this->modulesPath, 'Tags');
        lifecycleModule($this->modulesPath, 'Blog', ['requires' => ['tags']]);
        $this->modules->install('tags');
        $this->modules->install('blog');
        $this->modules->disable('blog');

        $this->modules->uninstall('tags');
    })->throws(ModuleException::class, 'cannot be uninstalled: "blog" depends on it');

    it('cannot uninstall a module that is not installed', function () {
        lifecycleModule($this->modulesPath, 'Blog');

        $this->modules->uninstall('blog');
    })->throws(ModuleException::class, 'not installed');

    it('needs the module folder to roll back', function () {
        lifecycleModule($this->modulesPath, 'Blog', [], 'lc_blog_posts');
        $this->modules->install('blog');
        File::deleteDirectory($this->modulesPath.'/Blog');
        $this->modules->sync();

        expect(fn () => $this->modules->uninstall('blog'))->toThrow(ModuleException::class, 'is missing')
            ->and(Schema::hasTable('lc_blog_posts'))->toBeTrue();
    });
});

describe('upgrade', function () {
    it('runs the new migrations and records the new version', function () {
        lifecycleModule($this->modulesPath, 'Blog', ['version' => '1.0.0'], 'lc_blog_posts');
        $this->modules->install('blog');

        fakeModule($this->modulesPath, 'Blog', ['version' => '1.1.0']);
        fakeModuleMigration($this->modulesPath, 'Blog', '2026_02_01_000000_create_lc_blog_tags_table', 'lc_blog_tags');

        $this->modules->upgrade('blog');

        $blog = module('blog');
        expect(Schema::hasTable('lc_blog_tags'))->toBeTrue()
            ->and($blog->installed_version)->toBe('1.1.0')
            ->and($blog->version)->toBe('1.1.0')
            ->and($blog->needsUpgrade())->toBeFalse()
            ->and($this->permissions->synced)->toBe(['blog', 'blog']);
        Event::assertDispatched(ModuleUpgraded::class, fn ($event) => $event->fromVersion === '1.0.0');
    });

    it('fails when the manifest version is not newer', function () {
        lifecycleModule($this->modulesPath, 'Blog');
        $this->modules->install('blog');

        $this->modules->upgrade('blog');
    })->throws(ModuleException::class, 'no upgrade available (installed 1.0.0, available 1.0.0)');

    it('fails for a module that is not installed', function () {
        lifecycleModule($this->modulesPath, 'Blog');

        $this->modules->upgrade('blog');
    })->throws(ModuleException::class, 'not installed');
});

describe('artisan commands', function () {
    it('installs, disables, enables, upgrades and uninstalls from the command line', function () {
        lifecycleModule($this->modulesPath, 'Blog', [], 'lc_blog_posts');

        $this->artisan('cms:module:install', ['slug' => 'blog'])
            ->expectsOutputToContain('Module "blog" installed (1.0.0) and enabled.')
            ->assertSuccessful();
        $this->artisan('cms:module:disable', ['slug' => 'blog'])->assertSuccessful();
        $this->artisan('cms:module:enable', ['slug' => 'blog'])->assertSuccessful();

        fakeModule($this->modulesPath, 'Blog', ['version' => '1.0.1']);
        $this->artisan('cms:module:upgrade', ['slug' => 'blog'])
            ->expectsOutputToContain('upgraded to 1.0.1')
            ->assertSuccessful();

        $this->artisan('cms:module:uninstall', ['slug' => 'blog', '--force' => true])->assertSuccessful();
        expect(Schema::hasTable('lc_blog_posts'))->toBeFalse();
    });

    it('shows the reason and fails when an action is not allowed', function () {
        $this->artisan('cms:module:install', ['slug' => 'nope'])
            ->expectsOutputToContain('Module "nope" was not found')
            ->assertFailed();
    });

    it('asks before uninstalling', function () {
        lifecycleModule($this->modulesPath, 'Blog', [], 'lc_blog_posts');
        $this->modules->install('blog');

        $this->artisan('cms:module:uninstall', ['slug' => 'blog'])
            ->expectsConfirmation('Uninstall "blog"? Its database tables and data will be deleted.', 'no')
            ->assertFailed();

        expect(Schema::hasTable('lc_blog_posts'))->toBeTrue();
    });
});
