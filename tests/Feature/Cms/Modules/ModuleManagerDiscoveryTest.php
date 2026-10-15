<?php

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use App\Cms\Support\CmsCache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->modulesPath = storage_path('framework/testing/modules-'.uniqid());
    File::ensureDirectoryExists($this->modulesPath);

    config(['cms.modules_path' => $this->modulesPath, 'cms.core_modules' => ['users']]);
    app()->forgetInstance(ModuleManager::class);
    $this->modules = app(ModuleManager::class);

    // The app cached the (empty) enabled list while booting; these tests create rows directly.
    $this->modules->flush();
});

afterEach(function () {
    File::deleteDirectory($this->modulesPath);
});

describe('discover', function () {
    it('reads every module folder, keyed and sorted by slug', function () {
        fakeModule($this->modulesPath, 'Pages');
        fakeModule($this->modulesPath, 'Blog');
        fakeModule($this->modulesPath, 'TourBookings');

        $manifests = $this->modules->discover();

        expect($manifests->keys()->all())->toBe(['blog', 'pages', 'tour-bookings'])
            ->and($manifests['blog']->name)->toBe('Blog')
            ->and($this->modules->errors())->toBe([]);
    });

    it('ignores folders without a module.json', function () {
        fakeModule($this->modulesPath, 'Blog');
        File::ensureDirectoryExists($this->modulesPath.'/Notes');

        expect($this->modules->discover()->keys()->all())->toBe(['blog']);
    });

    it('skips invalid manifests and reports them', function () {
        fakeModule($this->modulesPath, 'Blog');
        $broken = writeManifest($this->modulesPath, 'Broken', '{ not json');

        $manifests = $this->modules->discover();

        expect($manifests->keys()->all())->toBe(['blog'])
            ->and($this->modules->errors())->toHaveKey($broken)
            ->and($this->modules->errors()[$broken])->toContain('invalid JSON');
    });

    it('returns nothing when the modules folder does not exist', function () {
        File::deleteDirectory($this->modulesPath);

        expect($this->modules->discover())->toBeEmpty();
    });

    it('finds one manifest by slug', function () {
        fakeModule($this->modulesPath, 'Blog');

        expect($this->modules->manifest('blog')?->name)->toBe('Blog')
            ->and($this->modules->manifest('shop'))->toBeNull();
    });
});

describe('sync', function () {
    it('adds new modules as not installed', function () {
        fakeModule($this->modulesPath, 'Blog', ['version' => '1.2.0']);

        $result = $this->modules->sync();

        $blog = Module::where('slug', 'blog')->sole();
        expect($result->added)->toBe(['blog'])
            ->and($blog->name)->toBe('Blog')
            ->and($blog->version)->toBe('1.2.0')
            ->and($blog->isInstalled())->toBeFalse()
            ->and($blog->enabled)->toBeFalse();
    });

    it('marks modules as core from the manifest or from config', function () {
        fakeModule($this->modulesPath, 'Settings', ['core' => true]);
        fakeModule($this->modulesPath, 'Users');
        fakeModule($this->modulesPath, 'Blog');

        $this->modules->sync();

        expect(Module::where('is_core', true)->orderBy('slug')->pluck('slug')->all())->toBe(['settings', 'users']);
    });

    it('refreshes name and version but keeps the install state', function () {
        Module::factory()->installed()->create(['slug' => 'blog', 'name' => 'Old name', 'version' => '1.0.0']);
        fakeModule($this->modulesPath, 'Blog', ['name' => 'Blog', 'version' => '1.1.0']);

        $result = $this->modules->sync();

        $blog = Module::where('slug', 'blog')->sole();
        expect($result->updated)->toBe(['blog'])
            ->and($result->added)->toBe([])
            ->and($blog->name)->toBe('Blog')
            ->and($blog->version)->toBe('1.1.0')
            ->and($blog->installed_version)->toBe('1.0.0')
            ->and($blog->needsUpgrade())->toBeTrue()
            ->and($blog->enabled)->toBeTrue();
    });

    it('reports no changes on a second run', function () {
        fakeModule($this->modulesPath, 'Blog');

        $this->modules->sync();
        $result = $this->modules->sync();

        expect($result->hasChanges())->toBeFalse();
    });

    it('flags modules whose folder is gone and keeps their data', function () {
        Module::factory()->installed()->create(['slug' => 'shop']);

        $result = $this->modules->sync();

        $shop = Module::where('slug', 'shop')->sole();
        expect($result->missing)->toBe(['shop'])
            ->and($shop->is_missing)->toBeTrue()
            ->and($shop->isInstalled())->toBeTrue()
            ->and($shop->isActive())->toBeFalse();
    });

    it('restores a module when its folder comes back', function () {
        Module::factory()->installed()->missing()->create(['slug' => 'shop', 'name' => 'Shop']);
        fakeModule($this->modulesPath, 'Shop');

        $result = $this->modules->sync();

        expect($result->restored)->toBe(['shop'])
            ->and(Module::where('slug', 'shop')->sole()->isActive())->toBeTrue();
    });

    it('treats a module with an invalid manifest as missing and reports the error', function () {
        Module::factory()->installed()->create(['slug' => 'blog']);
        $file = writeManifest($this->modulesPath, 'Blog', validManifest(['version' => 'one']));

        $result = $this->modules->sync();

        expect($result->missing)->toBe(['blog'])
            ->and($result->hasErrors())->toBeTrue()
            ->and($result->invalid)->toHaveKey($file);
    });
});

describe('enabled', function () {
    it('returns installed, enabled modules with their folder, in sort order', function () {
        Module::factory()->create(['slug' => 'discovered']);
        Module::factory()->installed()->create(['slug' => 'pages', 'name' => 'Pages', 'sort_order' => 2]);
        Module::factory()->installed()->create(['slug' => 'blog', 'name' => 'Blog', 'sort_order' => 1]);
        Module::factory()->disabled()->create(['slug' => 'shop']);
        Module::factory()->installed()->missing()->create(['slug' => 'gone']);

        $enabled = $this->modules->enabled();

        expect($enabled->pluck('slug')->all())->toBe(['blog', 'pages'])
            ->and($enabled->first())->toBeInstanceOf(Module::class)
            ->and($enabled->first()->installed_at)->toBeInstanceOf(DateTimeInterface::class)
            ->and($this->modules->isEnabled('blog'))->toBeTrue()
            ->and($this->modules->isEnabled('shop'))->toBeFalse();
    });

    it('is cached until flushed', function () {
        Module::factory()->installed()->create(['slug' => 'blog']);
        expect($this->modules->enabled()->pluck('slug')->all())->toBe(['blog']);

        Module::factory()->installed()->create(['slug' => 'pages']);
        expect($this->modules->enabled()->pluck('slug')->all())->toBe(['blog']);

        $this->modules->flush();
        expect($this->modules->enabled()->pluck('slug')->sort()->values()->all())->toBe(['blog', 'pages']);
    });

    it('is flushed by sync', function () {
        expect($this->modules->enabled())->toBeEmpty();

        Module::factory()->installed()->create(['slug' => 'blog']);
        fakeModule($this->modulesPath, 'Blog');
        $this->modules->sync();

        expect($this->modules->enabled()->pluck('slug')->all())->toBe(['blog']);
    });

    it('returns nothing before the modules table exists', function () {
        Schema::drop('modules');

        expect($this->modules->enabled())->toBeEmpty();
    });
});

it('flushes the shared CMS cache', function () {
    $cms = app(CmsCache::class);
    $cms->rememberForever('admin.menu', fn () => 'cached menu');

    $this->modules->flush();

    expect($cms->rememberForever('admin.menu', fn () => 'fresh menu'))->toBe('fresh menu');
});
