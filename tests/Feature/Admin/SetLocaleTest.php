<?php

use App\Models\User;

it('uses the default locale', function () {
    $this->get('/admin/login');

    expect(app()->getLocale())->toBe('en');
});

it('switches the language with ?locale= and remembers it in the session', function () {
    $this->get('/admin/login?locale=ru')->assertSessionHas('locale', 'ru');
    expect(app()->getLocale())->toBe('ru');

    $this->get('/admin/login');
    expect(app()->getLocale())->toBe('ru');
});

it('ignores locales that are not configured', function () {
    $this->get('/admin/login?locale=de')->assertSessionMissing('locale');

    expect(app()->getLocale())->toBe('en');
});

it('uses the logged-in user\'s locale', function () {
    $this->actingAsAdmin(User::factory()->create(['locale' => 'ky']))->get('/admin');

    expect(app()->getLocale())->toBe('ky');
});

it('saves the chosen language as the user\'s preference', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAsAdmin($user)->get('/admin?locale=ru');

    expect($user->fresh()->locale)->toBe('ru')
        ->and(app()->getLocale())->toBe('ru');
});

it('prefers the session over the user\'s saved locale', function () {
    $this->withSession(['locale' => 'ru'])
        ->actingAsAdmin(User::factory()->create(['locale' => 'ky']))
        ->get('/admin');

    expect(app()->getLocale())->toBe('ru');
});
