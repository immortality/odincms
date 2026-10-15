<?php

use App\Cms\Settings\ApplySettings;
use App\Cms\Settings\Settings;
use Laravel\Fortify\Features;

beforeEach(function () {
    config([
        'cms.available_locales' => ['en', 'ru', 'ky'],
        'cms.locales' => ['en', 'ru', 'ky'],
        'cms.default_locale' => 'en',
        'app.name' => 'From env',
    ]);
    $this->settings = app(Settings::class);
    $this->settings->flush();
    $this->apply = fn () => app(ApplySettings::class)->apply();
});

it('leaves the config alone when nothing is saved', function () {
    ($this->apply)();

    expect(config('app.name'))->toBe('From env')
        ->and(config('cms.locales'))->toBe(['en', 'ru', 'ky'])
        ->and(config('cms.default_locale'))->toBe('en');
});

it('applies the site name in the default language', function () {
    $this->settings->set('site.name', ['en' => 'Silk Road Tours', 'ru' => 'Шёлковый путь']);
    $this->settings->set('locales.default', 'ru');
    $this->settings->set('locales.active', ['en', 'ru']);

    ($this->apply)();

    expect(config('app.name'))->toBe('Шёлковый путь');
});

it('applies the active and default languages', function () {
    $this->settings->set('locales.active', ['ru', 'ky']);
    $this->settings->set('locales.default', 'ky');

    ($this->apply)();

    expect(config('cms.locales'))->toBe(['ru', 'ky'])
        ->and(config('cms.default_locale'))->toBe('ky')
        ->and(config('app.locale'))->toBe('ky');
});

it('ignores languages that are not available and a default that is not active', function () {
    $this->settings->set('locales.active', ['de', 'ru']);
    $this->settings->set('locales.default', 'en');

    ($this->apply)();

    expect(config('cms.locales'))->toBe(['ru'])
        ->and(config('cms.default_locale'))->toBe('ru');
});

it('applies the mail sender', function () {
    $this->settings->set('mail.from_address', 'tours@example.com');
    $this->settings->set('mail.from_name', 'Silk Road Tours');

    ($this->apply)();

    expect(config('mail.from'))->toBe(['address' => 'tours@example.com', 'name' => 'Silk Road Tours']);
});

it('turns on public registration', function () {
    expect(in_array(Features::registration(), config('fortify.features'), true))->toBeFalse();

    $this->settings->set('users.registration', true);
    ($this->apply)();
    ($this->apply)(); // twice: added once

    expect(array_count_values(config('fortify.features'))[Features::registration()] ?? 0)->toBe(1);
});
