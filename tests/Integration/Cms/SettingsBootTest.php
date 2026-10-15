<?php

use App\Cms\Settings\Settings;
use App\Models\User;
use Spatie\Permission\Models\Role;

// Saved settings change the configuration of the next request: boot a fresh application.

it('shows a changed site name on the frontend', function () {
    app(Settings::class)->set('site.name', ['en' => 'Silk Road Tours']);

    $this->refreshApplication();

    $this->get('/')->assertOk()->assertSee('Silk Road Tours');
    expect(config('app.name'))->toBe('Silk Road Tours');
});

it('applies the active languages to the admin and content forms', function () {
    app(Settings::class)->set('locales.active', ['en', 'ky']);
    app(Settings::class)->set('locales.default', 'en');

    $this->refreshApplication();

    expect(config('cms.locales'))->toBe(['en', 'ky']);
    $this->get('/admin/login?locale=ru')->assertSessionMissing('locale'); // ru is not active any more
});

it('opens public registration only when it is switched on', function () {
    $this->get('/admin/register')->assertNotFound();

    app(Settings::class)->set('users.registration', true);
    Role::findOrCreate('user', 'web');
    $this->refreshApplication();

    $this->get('/admin/login')->assertSee('Create an account');
    $this->get('/admin/register')->assertOk()->assertSee('name="password_confirmation"', false);

    $this->post('/admin/register', [
        'name' => 'Visitor', 'email' => 'visitor@example.com', 'password' => 'a-Strong-pass-123', 'password_confirmation' => 'a-Strong-pass-123',
    ])->assertRedirect('/');

    $visitor = User::where('email', 'visitor@example.com')->sole();
    expect($visitor->hasRole('user'))->toBeTrue()
        ->and($visitor->hasVerifiedEmail())->toBeFalse()
        ->and($visitor->can('admin.access'))->toBeFalse();
});
