<?php

use App\Cms\Admin\AdminMenu;
use App\Cms\Modules\Exceptions\ModuleException;
use App\Cms\Modules\ModuleLoader;
use App\Cms\Modules\ModuleManager;
use App\Models\User;
use Modules\Groups\Models\Group;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->modules = app(ModuleManager::class);
    $this->modules->sync();
});

it('installs Groups, Users and Permissions in dependency order', function () {
    // Users and Permissions need Groups.
    expect(fn () => $this->modules->install('users'))->toThrow(ModuleException::class, 'requires "groups"');

    foreach (['groups', 'users', 'permissions'] as $slug) {
        $this->modules->install($slug);
    }

    // The Groups seeder ran; super admins have every module permission.
    $super = Group::findByName('super-admin');
    expect(Group::orderBy('id')->pluck('name')->all())->toBe(['super-admin', 'admin', 'user'])
        ->and(Group::findByName('admin')->hasPermissionTo('admin.access'))->toBeTrue()
        ->and($super->permissions->pluck('name')->sort()->values()->all())->toBe([
            'admin.access',
            'groups.create', 'groups.delete', 'groups.edit', 'groups.view',
            'permissions.edit', 'permissions.view',
            'users.create', 'users.delete', 'users.edit', 'users.view',
        ]);
});

it('puts the three modules in the Users section of the admin menu', function () {
    foreach (['groups', 'users', 'permissions'] as $slug) {
        $this->modules->install($slug);
    }
    app(ModuleLoader::class)->load();

    $admin = User::factory()->create()->assignRole('super-admin');
    $users = collect(app(AdminMenu::class)->build($admin))->firstWhere('label', 'Users');

    expect(array_column($users['items'], 'label'))->toBe(['Users', 'Groups', 'Permissions']);

    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/groups')->assertOk();
    $this->actingAs($admin)->get('/admin/permissions')->assertOk();
    $this->actingAs($admin)->get('/admin/users/profile')->assertOk();
});

it('protects the core modules', function () {
    foreach (['groups', 'users', 'permissions'] as $slug) {
        $this->modules->install($slug);
    }

    expect(fn () => $this->modules->uninstall('users'))->toThrow(ModuleException::class, 'core module')
        ->and(fn () => $this->modules->disable('groups'))->toThrow(ModuleException::class, 'core module')
        ->and(Permission::where('name', 'users.view')->exists())->toBeTrue();
});
