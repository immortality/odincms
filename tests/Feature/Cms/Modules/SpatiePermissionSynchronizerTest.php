<?php

use App\Cms\Modules\Contracts\PermissionSynchronizer;
use App\Cms\Modules\Manifest;
use App\Cms\Modules\Support\SpatiePermissionSynchronizer;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\Modules\FakeModuleServiceProvider;

function blogManifest(array $permissions): Manifest
{
    return new Manifest(
        name: 'Blog',
        slug: 'blog',
        version: '1.0.0',
        provider: FakeModuleServiceProvider::class,
        path: base_path('modules/Blog'),
        permissions: $permissions,
    );
}

function permissionNames(): array
{
    return Permission::orderBy('name')->pluck('name')->all();
}

it('is the bound permission synchronizer', function () {
    expect(app(PermissionSynchronizer::class))->toBeInstanceOf(SpatiePermissionSynchronizer::class);
});

it('creates the declared permissions and admin.access, and grants them to the super admins', function () {
    app(PermissionSynchronizer::class)->sync(blogManifest(['blog.view', 'blog.create']));

    $superAdmin = Role::findByName('super-admin', 'web');

    expect(permissionNames())->toBe(['admin.access', 'blog.create', 'blog.view'])
        ->and($superAdmin->permissions->pluck('name')->sort()->values()->all())->toBe(['admin.access', 'blog.create', 'blog.view']);
});

it('removes permissions the manifest no longer declares, and keeps other modules\' permissions', function () {
    Permission::findOrCreate('pages.view', 'web');
    $sync = app(PermissionSynchronizer::class);

    $sync->sync(blogManifest(['blog.view', 'blog.create', 'blog.delete']));
    $sync->sync(blogManifest(['blog.view', 'blog.publish']));

    expect(permissionNames())->toBe(['admin.access', 'blog.publish', 'blog.view', 'pages.view']);
});

it('is safe to run twice', function () {
    $sync = app(PermissionSynchronizer::class);

    $sync->sync(blogManifest(['blog.view']));
    $sync->sync(blogManifest(['blog.view']));

    expect(permissionNames())->toBe(['admin.access', 'blog.view']);
});

it('removes all of a module\'s permissions, including their group assignments', function () {
    $sync = app(PermissionSynchronizer::class);
    $sync->sync(blogManifest(['blog.view', 'blog.create']));
    Permission::findOrCreate('blogger.view', 'web'); // another module whose slug starts with "blog"
    $editor = Role::findOrCreate('editor', 'web')->givePermissionTo('blog.view');

    $sync->remove('blog');

    expect(permissionNames())->toBe(['admin.access', 'blogger.view'])
        ->and($editor->fresh()->permissions)->toBeEmpty();
});

it('clears the permission cache, so a removed permission stops working immediately', function () {
    $sync = app(PermissionSynchronizer::class);
    $sync->sync(blogManifest(['blog.view']));
    $user = User::factory()->create()->givePermissionTo('blog.view');
    expect($user->can('blog.view'))->toBeTrue();

    $sync->remove('blog');

    expect($user->fresh()->can('blog.view'))->toBeFalse();
});
