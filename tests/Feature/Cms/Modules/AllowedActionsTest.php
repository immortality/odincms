<?php

use App\Cms\Modules\Exceptions\ModuleException;
use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->modulesPath = storage_path('framework/testing/actions-'.uniqid());
    File::ensureDirectoryExists($this->modulesPath);
    config([
        'cms.modules_path' => $this->modulesPath,
        'cms.core_modules' => ['users', 'categories'],
        'cms.disableable_core_modules' => ['categories'],
    ]);
    app()->forgetInstance(ModuleManager::class);
    $this->modules = app(ModuleManager::class);
});

afterEach(function () {
    File::deleteDirectory($this->modulesPath);
});

/**
 * Create module folders, sync, and give each module a state: "not installed", "enabled" or "disabled".
 *
 * @param  array<string, array{0: string, 1?: array<string, mixed>}>  $modules  folder => [state, manifest]
 */
function withModules(string $path, array $modules): void
{
    foreach ($modules as $folder => [$state, $manifest]) {
        fakeModule($path, $folder, $manifest ?? []);
    }
    app(ModuleManager::class)->sync();

    foreach ($modules as $folder => [$state]) {
        if ($state !== 'not installed') {
            Module::where('slug', strtolower($folder))->update([
                'installed_at' => now(), 'installed_version' => '1.0.0', 'enabled' => $state === 'enabled',
            ]);
        }
    }
    app(ModuleManager::class)->flush();
}

function actions(string $slug): array
{
    return app(ModuleManager::class)->allowedActions(Module::where('slug', $slug)->sole());
}

it('offers install for a module whose dependencies are enabled', function () {
    withModules($this->modulesPath, [
        'Tags' => ['enabled', []],
        'Blog' => ['not installed', ['requires' => ['tags']]],
        'Shop' => ['not installed', ['requires' => ['payments']]],
    ]);

    expect(actions('blog'))->toBe(['install'])
        ->and(actions('shop'))->toBe([]);
});

it('offers disable and uninstall for an enabled module nobody depends on', function () {
    withModules($this->modulesPath, ['Blog' => ['enabled', []]]);

    expect(actions('blog'))->toBe(['disable', 'uninstall']);
});

it('offers enable and uninstall for a disabled module', function () {
    withModules($this->modulesPath, ['Blog' => ['disabled', []]]);

    expect(actions('blog'))->toBe(['enable', 'uninstall']);
});

it('offers neither disable nor uninstall while other modules depend on it', function () {
    withModules($this->modulesPath, [
        'Tags' => ['enabled', []],
        'Blog' => ['enabled', ['requires' => ['tags']]],
    ]);

    expect(actions('tags'))->toBe([]);
});

it('offers disable but not uninstall when only a disabled module depends on it', function () {
    withModules($this->modulesPath, [
        'Tags' => ['enabled', []],
        'Blog' => ['disabled', ['requires' => ['tags']]],
    ]);

    expect(actions('tags'))->toBe(['disable']);
});

it('does not offer enable while a dependency is disabled', function () {
    withModules($this->modulesPath, [
        'Tags' => ['disabled', []],
        'Blog' => ['disabled', ['requires' => ['tags']]],
    ]);

    expect(actions('blog'))->toBe(['uninstall']);
});

it('protects core modules', function () {
    withModules($this->modulesPath, [
        'Users' => ['enabled', []],
        'Categories' => ['enabled', []],
    ]);

    expect(actions('users'))->toBe([])
        ->and(actions('categories'))->toBe(['disable']);
});

it('offers upgrade when the manifest version is newer', function () {
    withModules($this->modulesPath, ['Blog' => ['enabled', ['version' => '1.2.0']]]);

    expect(actions('blog'))->toBe(['disable', 'upgrade', 'uninstall']);
});

it('offers nothing while the folder is missing', function () {
    withModules($this->modulesPath, ['Blog' => ['enabled', []]]);
    File::deleteDirectory($this->modulesPath.'/Blog');
    $this->modules->sync();

    expect(actions('blog'))->toBe([]);
});

it('allows exactly what the lifecycle methods allow', function () {
    withModules($this->modulesPath, [
        'Tags' => ['enabled', []],
        'Blog' => ['disabled', ['requires' => ['tags']]],
        'Users' => ['enabled', []],
    ]);

    // Offered actions succeed...
    foreach (actions('tags') as $action) {
        expect(fn () => $this->modules->{$action}('tags'))->not->toThrow(Throwable::class);
    }

    // ...and the ones not offered are refused.
    expect(fn () => $this->modules->uninstall('tags'))->toThrow(ModuleException::class)
        ->and(fn () => $this->modules->disable('users'))->toThrow(ModuleException::class);
});

describe('needsSync', function () {
    it('is false right after a sync', function () {
        fakeModule($this->modulesPath, 'Blog');
        $this->modules->sync();

        expect($this->modules->needsSync())->toBeFalse();
    });

    it('notices a new folder, a new version and a removed folder', function () {
        fakeModule($this->modulesPath, 'Blog');
        $this->modules->sync();

        fakeModule($this->modulesPath, 'Pages');
        expect($this->modules->needsSync())->toBeTrue();
        $this->modules->sync();

        fakeModule($this->modulesPath, 'Blog', ['version' => '1.1.0']);
        expect($this->modules->needsSync())->toBeTrue();
        $this->modules->sync();

        File::deleteDirectory($this->modulesPath.'/Pages');
        expect($this->modules->needsSync())->toBeTrue();
        $this->modules->sync();

        expect($this->modules->needsSync())->toBeFalse();
    });
});
