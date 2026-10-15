<?php

use App\Cms\Settings\Settings;
use App\Models\User;

beforeEach(function () {
    app(Settings::class)->flush();
});

it('shows the site normally', function () {
    $this->get('/')->assertOk();
});

it('shows visitors a 503 page with the message while in maintenance', function () {
    app(Settings::class)->set('maintenance.enabled', true);
    app(Settings::class)->set('maintenance.message', ['en' => 'Back on Monday.', 'ru' => 'Вернёмся в понедельник.']);

    $this->get('/')->assertStatus(503)->assertSee('Back on Monday.')->assertHeader('Retry-After');
    $this->get('/?locale=ru')->assertStatus(503)->assertSee('Вернёмся в понедельник.');
});

it('keeps the admin panel and its login page working', function () {
    app(Settings::class)->set('maintenance.enabled', true);

    $this->get('/admin/login')->assertOk();
    $this->actingAsAdmin()->get('/admin')->assertOk();
});

it('lets admin users see the site, but not other logged-in users', function () {
    app(Settings::class)->set('maintenance.enabled', true);

    $this->actingAsAdmin()->get('/')->assertOk();
    $this->actingAs(User::factory()->create())->get('/')->assertStatus(503);
});
