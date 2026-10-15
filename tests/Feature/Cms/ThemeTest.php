<?php

use App\Cms\Settings\Settings;
use App\Models\User;

/**
 * Step 8.1: the default theme.
 */
beforeEach(function () {
    config(['cms.locales' => ['en', 'ru', 'ky'], 'cms.default_locale' => 'en']);
    $this->useModule('settings');
});

it('shows the site name and tagline in the visitor\'s language', function () {
    app(Settings::class)->setMany([
        'site.name' => ['en' => 'Silk Road Tours', 'ru' => 'Туры по Шёлковому пути'],
        'site.tagline' => ['en' => 'Travel across Central Asia', 'ru' => 'Путешествия по Центральной Азии'],
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Silk Road Tours</title>', false)
        ->assertSee('<meta name="description" content="Travel across Central Asia">', false);

    $this->get('/ru')
        ->assertOk()
        ->assertSee('Туры по Шёлковому пути')
        ->assertSee('Путешествия по Центральной Азии');
});

it('shows the admin panel button only to those who may use it', function () {
    $this->get('/')->assertDontSee(route('admin.dashboard'));
    $this->actingAsAdmin()->get('/')->assertSee(route('admin.dashboard'));
});

it('renders a missing page in the theme, in the visitor\'s language', function () {
    $this->get('/no/such/page')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('<meta name="robots" content="noindex">', false);

    $this->get('/ru/no/such/page')->assertNotFound()->assertSee('Страница не найдена');
});

it('renders admin errors in the admin\'s look', function () {
    $this->useModule('users');

    $this->actingAs(User::factory()->create())->get(route('admin.users.index'))
        ->assertForbidden()
        ->assertSee('odinadmin/css/style.css', false)
        ->assertSee('Access denied');
});

it('has a server error page that needs nothing but the framework', function () {
    $html = view('errors.500')->render();

    expect($html)->toContain('Server error')->not->toContain('@vite');
});
