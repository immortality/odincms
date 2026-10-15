<?php

use App\Cms\Modules\Module;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('has no folder in public/ that would shadow the admin URL', function () {
    // The web server (artisan serve, Apache) serves a real directory instead of passing
    // the request to Laravel, so public/admin would turn /admin into a 404.
    expect(is_dir(public_path(config('cms.admin_prefix'))))->toBeFalse();
});

describe('the admin middleware', function () {
    it('sends guests to the login page', function () {
        $this->get('/admin')->assertRedirect('/admin/login');
    });

    it('forbids users without admin.access', function () {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    });

    it('lets users whose group has admin.access in', function () {
        $this->actingAsUserWith([])->get('/admin')->assertOk();
    });

    it('lets super admins in without any explicit permission', function () {
        $this->actingAsAdmin()->get('/admin')->assertOk();
    });

    it('asks users to verify their email address first', function () {
        $this->actingAsAdmin(User::factory()->unverified()->create())
            ->get('/admin')
            ->assertRedirect('/admin/email/verify');
    });

    it('signs out a user who was deactivated while logged in', function () {
        $user = User::factory()->create();
        $this->actingAsAdmin($user);
        $user->forceFill(['is_active' => false])->save();

        $this->get('/admin')
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email' => 'This account has been deactivated.']);

        $this->assertGuest();
    });
});

describe('super admins', function () {
    it('pass every permission check', function () {
        $admin = User::factory()->create()->assignRole(Role::findOrCreate('super-admin', 'web'));

        expect($admin->can('admin.access'))->toBeTrue()
            ->and($admin->can('anything.at-all'))->toBeTrue();
    });

    it('are the only ones who do', function () {
        Permission::findOrCreate('blog.view', 'web');
        $editor = User::factory()->create()->assignRole(Role::findOrCreate('editor', 'web')->givePermissionTo('blog.view'));

        expect($editor->can('blog.view'))->toBeTrue()
            ->and($editor->can('blog.delete'))->toBeFalse()
            ->and($editor->can('admin.access'))->toBeFalse();
    });
});

describe('the dashboard', function () {
    it('shows user and module counts and recent logins', function () {
        $admin = User::factory()->create(['name' => 'Aibek Admin']);
        $admin->forceFill(['last_login_at' => now()->subMinutes(5)])->save();
        User::factory()->inactive()->create();
        Module::factory()->installed()->create();
        Module::factory()->disabled()->create();
        Module::factory()->create();

        $this->actingAsAdmin($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Welcome back, Aibek Admin.')
            ->assertSeeInOrder(['Users', '2', '1 active'])
            ->assertSeeInOrder(['Enabled modules', '1', '2 installed'])
            ->assertSeeInOrder(['Available modules', '3'])
            ->assertSee('5 minutes ago');
    });

    it('is the active item of the admin menu', function () {
        $this->actingAsAdmin()->get('/admin')
            ->assertSee('class="nav-link active"', false);
    });
});
