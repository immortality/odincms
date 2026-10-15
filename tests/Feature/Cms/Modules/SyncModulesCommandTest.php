<?php

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->modulesPath = storage_path('framework/testing/modules-'.uniqid());
    File::ensureDirectoryExists($this->modulesPath);

    config(['cms.modules_path' => $this->modulesPath]);
    app()->forgetInstance(ModuleManager::class);
});

afterEach(function () {
    File::deleteDirectory($this->modulesPath);
});

it('syncs the modules folder and lists the modules', function () {
    fakeModule($this->modulesPath, 'Blog');
    Module::factory()->installed()->create(['slug' => 'shop', 'name' => 'Shop']);

    $exitCode = Artisan::call('cms:modules:sync');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toMatch('/Added \.+ blog/')
        ->and($output)->toMatch('/Missing \.+ shop/')
        ->and($output)->toMatch('/\| blog\s+\| Blog .*\| not installed \|/')
        ->and($output)->toMatch('/\| shop\s+\| Shop .*\| missing\s+\|/')
        ->and(Module::where('slug', 'blog')->exists())->toBeTrue();
});

it('says when nothing changed', function () {
    $this->artisan('cms:modules:sync')
        ->expectsOutputToContain('up to date')
        ->assertSuccessful();
});

it('fails and prints the error for an invalid manifest', function () {
    writeManifest($this->modulesPath, 'Blog', validManifest(['slug' => 'Blog']));

    $this->artisan('cms:modules:sync')
        ->expectsOutputToContain('kebab-case')
        ->assertFailed();
});
