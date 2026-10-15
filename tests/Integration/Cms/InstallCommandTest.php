<?php

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The core modules that exist in modules/ (more appear as the phases are built).
 *
 * @return list<string>
 */
function presentCoreModules(): array
{
    $present = app(ModuleManager::class)->discover()->keys()->all();
    $core = array_values(array_intersect(config('cms.core_modules'), $present));
    sort($core);

    return $core;
}

it('installs the core modules and creates the first super admin from the options', function () {
    $this->artisan('cms:install', ['--name' => 'Aibek', '--email' => 'aibek@example.com', '--password' => 'a-Strong-pass-123'])
        ->expectsOutputToContain('Super admin aibek@example.com created.')
        ->expectsOutputToContain('Sign in at')
        ->assertSuccessful();

    expect(presentCoreModules())->toContain('groups', 'users', 'permissions', 'modules')
        ->and(Module::installed()->orderBy('slug')->pluck('slug')->all())->toBe(presentCoreModules());

    $admin = User::where('email', 'aibek@example.com')->sole();
    expect($admin->hasRole('super-admin'))->toBeTrue()
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('a-Strong-pass-123', $admin->password))->toBeTrue();

    // The new admin can sign in and use the admin.
    $this->post('/admin/login', ['email' => 'aibek@example.com', 'password' => 'a-Strong-pass-123'])->assertRedirect('/admin');
    $this->get('/admin')->assertOk()->assertSee('Welcome back, Aibek.');
});

it('asks for the super admin when no options are given', function () {
    $this->artisan('cms:install')
        ->expectsQuestion('Name', 'Elena')
        ->expectsQuestion('Email', 'elena@example.com')
        ->expectsQuestion('Password (at least 8 characters)', 'a-Strong-pass-123')
        ->expectsQuestion('Repeat the password', 'a-Strong-pass-123')
        ->assertSuccessful();

    expect(User::where('email', 'elena@example.com')->sole()->hasRole('super-admin'))->toBeTrue();
});

it('refuses an invalid super admin and creates nothing', function () {
    $this->artisan('cms:install')
        ->expectsQuestion('Name', 'Elena')
        ->expectsQuestion('Email', 'not-an-email')
        ->expectsQuestion('Password (at least 8 characters)', 'short')
        ->expectsQuestion('Repeat the password', 'other')
        ->expectsOutputToContain('must be a valid email address')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('can be run again: skips installed modules and asks before adding another super admin', function () {
    $this->artisan('cms:install', ['--name' => 'Aibek', '--email' => 'aibek@example.com', '--password' => 'a-Strong-pass-123'])->assertSuccessful();

    $this->artisan('cms:install')
        ->expectsOutputToContain('already installed')
        ->expectsConfirmation('A super admin already exists. Create another one?', 'no')
        ->assertSuccessful();

    expect(User::count())->toBe(1);
});

it('installs only the modules with --no-admin', function () {
    $this->artisan('cms:install', ['--no-admin' => true])->assertSuccessful();

    expect(Module::installed()->count())->toBe(count(presentCoreModules()))
        ->and(User::count())->toBe(0);
});
