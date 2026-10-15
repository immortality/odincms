<?php

use App\Cms\Settings\Settings;

beforeEach(function () {
    $this->useModule('settings');
    app(Settings::class)->flush();
});

it('opens the first section and lists all sections', function () {
    $this->actingAsAdmin()->get(route('admin.settings.index'))->assertRedirect(route('admin.settings.show', 'site'));

    $this->actingAsAdmin()->get(route('admin.settings.show', 'site'))
        ->assertOk()
        ->assertSeeInOrder(['Site', 'Languages', 'Email', 'Users', 'Maintenance'])
        ->assertSee('Site name');
});

it('saves the site name in several languages', function () {
    $this->actingAsAdmin()->put(route('admin.settings.update', 'site'), ['name' => ['en' => 'Silk Road', 'ru' => 'Шёлковый путь', 'ky' => '']])
        ->assertRedirect(route('admin.settings.show', 'site'))
        ->assertSessionHas('success', 'Site settings have been saved.');

    expect(app(Settings::class)->get('site.name'))->toBe(['en' => 'Silk Road', 'ru' => 'Шёлковый путь']);
});

it('requires the site name', function () {
    $this->actingAsAdmin()->put(route('admin.settings.update', 'site'), ['name' => []])->assertSessionHasErrors('name');
});

it('saves the languages, and the default must be active', function () {
    $this->actingAsAdmin()->put(route('admin.settings.update', 'locales'), ['active' => ['en', 'ru'], 'default' => 'ky'])
        ->assertSessionHasErrors(['default' => 'The default language must be one of the active languages.']);

    $this->actingAsAdmin()->put(route('admin.settings.update', 'locales'), ['active' => ['de'], 'default' => 'en'])
        ->assertSessionHasErrors('active.0');

    $this->actingAsAdmin()->put(route('admin.settings.update', 'locales'), ['active' => ['en', 'ru'], 'default' => 'ru'])
        ->assertSessionHasNoErrors();

    expect(app(Settings::class)->get('locales.active'))->toBe(['en', 'ru'])
        ->and(app(Settings::class)->get('locales.default'))->toBe('ru');
});

it('saves checkboxes and validates emails', function () {
    $this->actingAsAdmin()->put(route('admin.settings.update', 'users'), ['registration' => '1']);
    $this->actingAsAdmin()->put(route('admin.settings.update', 'mail'), ['from_address' => 'not-an-email'])->assertSessionHasErrors('from_address');

    expect(app(Settings::class)->get('users.registration'))->toBeTrue();

    $this->actingAsAdmin()->put(route('admin.settings.update', 'users'), ['registration' => '0']);
    expect(app(Settings::class)->get('users.registration'))->toBeFalse();
});

it('shows the saved values in the form', function () {
    app(Settings::class)->set('mail.from_address', 'tours@example.com');

    $this->actingAsAdmin()->get(route('admin.settings.show', 'mail'))->assertSee('value="tours@example.com"', false);
});

it('shows sections that other modules register', function () {
    app(Settings::class)->section('blog', 'Blog', [
        'per_page' => ['type' => 'number', 'label' => 'Posts per page', 'default' => 10, 'rules' => ['integer', 'min:1']],
    ], module: 'blog');

    $this->actingAsAdmin()->get(route('admin.settings.show', 'blog'))->assertOk()->assertSee('Posts per page')->assertSee('value="10"', false);
    $this->actingAsAdmin()->put(route('admin.settings.update', 'blog'), ['per_page' => '0'])->assertSessionHasErrors('per_page');
    $this->actingAsAdmin()->put(route('admin.settings.update', 'blog'), ['per_page' => '25']);

    expect(app(Settings::class)->get('blog.per_page'))->toBe(25);
});

it('returns 404 for an unknown section', function () {
    $this->actingAsAdmin()->get(route('admin.settings.show', 'nope'))->assertNotFound();
});

it('needs settings.view to look and settings.edit to save', function () {
    $this->actingAsUserWith([])->get(route('admin.settings.show', 'site'))->assertForbidden();

    $this->actingAsUserWith(['settings.view'])->get(route('admin.settings.show', 'site'))
        ->assertOk()
        ->assertSee('<fieldset disabled', false)
        ->assertDontSee('>Save</button>', false);
    $this->actingAsUserWith(['settings.view'])->put(route('admin.settings.update', 'site'), ['name' => ['en' => 'X']])->assertForbidden();
});
