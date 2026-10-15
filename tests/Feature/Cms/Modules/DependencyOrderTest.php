<?php

use App\Cms\Modules\Exceptions\ModuleException;
use App\Cms\Modules\ModuleManager;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->modulesPath = storage_path('framework/testing/order-'.uniqid());
    File::ensureDirectoryExists($this->modulesPath);
    config(['cms.modules_path' => $this->modulesPath]);
    app()->forgetInstance(ModuleManager::class);
    $this->modules = app(ModuleManager::class);
});

afterEach(function () {
    File::deleteDirectory($this->modulesPath);
});

it('puts every module after the modules it requires', function () {
    fakeModule($this->modulesPath, 'Comments', ['requires' => ['blog', 'users']]);
    fakeModule($this->modulesPath, 'Blog', ['requires' => ['categories']]);
    fakeModule($this->modulesPath, 'Categories');
    fakeModule($this->modulesPath, 'Users', ['requires' => ['groups']]);
    fakeModule($this->modulesPath, 'Groups');

    expect($this->modules->dependencyOrder(['comments', 'groups']))
        ->toBe(['categories', 'blog', 'groups', 'users', 'comments']);
});

it('adds required modules that were not asked for', function () {
    fakeModule($this->modulesPath, 'Users', ['requires' => ['groups']]);
    fakeModule($this->modulesPath, 'Groups');

    expect($this->modules->dependencyOrder(['users']))->toBe(['groups', 'users']);
});

it('lists each module once', function () {
    fakeModule($this->modulesPath, 'Users', ['requires' => ['groups']]);
    fakeModule($this->modulesPath, 'Permissions', ['requires' => ['groups']]);
    fakeModule($this->modulesPath, 'Groups');

    expect($this->modules->dependencyOrder(['users', 'permissions', 'groups']))->toBe(['groups', 'users', 'permissions']);
});

it('fails when a required module is not in the modules folder', function () {
    fakeModule($this->modulesPath, 'Users', ['requires' => ['groups']]);

    $this->modules->dependencyOrder(['users']);
})->throws(ModuleException::class, 'Module "users" requires "groups"');

it('fails for an unknown module', function () {
    $this->modules->dependencyOrder(['nope']);
})->throws(ModuleException::class, 'Module "nope" was not found');

it('fails on modules that require each other', function () {
    fakeModule($this->modulesPath, 'Alpha', ['requires' => ['beta']]);
    fakeModule($this->modulesPath, 'Beta', ['requires' => ['gamma']]);
    fakeModule($this->modulesPath, 'Gamma', ['requires' => ['alpha']]);

    $this->modules->dependencyOrder(['alpha']);
})->throws(ModuleException::class, 'alpha → beta → gamma → alpha');
