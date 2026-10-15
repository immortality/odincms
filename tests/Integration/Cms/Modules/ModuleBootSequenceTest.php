<?php

use App\Cms\Modules\ModuleLoader;
use App\Cms\Modules\ModuleManager;
use Illuminate\Support\Facades\File;

// Uses the real modules/ folder, because a freshly booted app reads config('cms.modules_path')
// from the config files. The module folder is removed after each test.

afterEach(function () {
    File::deleteDirectory(base_path('modules/BootSequence'));
});

it('loads enabled modules while the application boots', function () {
    $slug = bootableModule(base_path('modules'), 'BootSequence');
    app(ModuleManager::class)->sync();
    app(ModuleManager::class)->install($slug);

    // A new application instance boots exactly like a real request.
    $this->refreshApplication();

    expect(app(ModuleLoader::class)->loaded())->toBe(['boot-sequence']);
    $this->get('/boot-sequence-hello')->assertOk()->assertSee('Hello from BootSequence module');
});

it('does not load the module after it is disabled', function () {
    $slug = bootableModule(base_path('modules'), 'BootSequence');
    app(ModuleManager::class)->sync();
    app(ModuleManager::class)->install($slug);
    app(ModuleManager::class)->disable($slug);

    $this->refreshApplication();

    expect(app(ModuleLoader::class)->loaded())->toBe([]);
    $this->get('/boot-sequence-hello')->assertNotFound();
});
