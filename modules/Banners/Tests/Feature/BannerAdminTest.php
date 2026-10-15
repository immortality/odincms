<?php

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Banners\Models\Banner;

beforeEach(function () {
    config(['cms.locales' => ['en', 'ru', 'ky'], 'cms.default_locale' => 'en']);

    $this->useModule('banners');
});

it('is an optional module: it can be disabled and uninstalled', function () {
    $module = Module::where('slug', 'banners')->sole();

    expect($module->is_core)->toBeFalse()
        ->and(app(ModuleManager::class)->allowedActions($module))->toContain('disable', 'uninstall');
});

it('sends guests to the login page and forbids users without permission', function () {
    $this->get(route('admin.banners.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.banners.index'))->assertForbidden();
    $this->actingAsUserWith(['banners.view'])->get(route('admin.banners.index'))->assertOk();
    $this->actingAsUserWith(['banners.view'])->get(route('admin.banners.create'))->assertForbidden();
});

it('lists banners by position, searches and filters them', function () {
    Banner::factory()->published()->create(['title' => ['en' => 'Second slide'], 'position' => 2]);
    Banner::factory()->create(['title' => ['en' => 'First slide'], 'highlight' => ['en' => 'growth', 'ru' => 'рост'], 'position' => 1]);

    $this->actingAsAdmin()->get(route('admin.banners.index'))->assertOk()
        ->assertSeeInOrder(['First slide growth', 'Second slide']);

    $this->actingAsAdmin()->get(route('admin.banners.index', ['search' => 'рост']))
        ->assertSee('First slide')->assertDontSee('Second slide');
    $this->actingAsAdmin()->get(route('admin.banners.index', ['status' => 'published']))
        ->assertSee('Second slide')->assertDontSee('First slide');
});

it('creates a banner with translations, extras and an image', function () {
    Storage::fake('public');

    $this->actingAsAdmin()->get(route('admin.banners.create'))->assertOk()
        ->assertSee('name="title[en]"', false)->assertSee('name="chip_1_icon"', false)->assertSee('name="image"', false);

    $this->actingAsAdmin()->post(route('admin.banners.store'), [
        'title' => ['en' => 'Agency vision for the', 'ru' => 'Видение агентства', 'ky' => ''],
        'highlight' => ['en' => 'next generation'],
        'text' => ['en' => 'We turn data into strategy.'],
        'button_label' => ['en' => 'Get started'],
        'button_url' => '#contact',
        'badge_label' => ['en' => 'NEW'],
        'badge_text' => ['en' => 'Growth Audit is live'],
        'chip_1_text' => ['en' => 'Fast onboarding'],
        'chip_1_icon' => 'bi-lightning-charge-fill',
        'chip_2_text' => ['en' => 'Certified'],
        'chip_2_icon' => '',
        'position' => '3',
        'status' => 'published',
        'image' => UploadedFile::fake()->image('hero.png', 1200, 1240),
    ])->assertRedirect()->assertSessionHas('success');

    $banner = Banner::sole();
    expect($banner->getTranslations('title'))->toBe(['en' => 'Agency vision for the', 'ru' => 'Видение агентства'])
        ->and($banner->label())->toBe('Agency vision for the next generation')
        ->and($banner->chip_1_icon)->toBe('lightning-charge-fill')
        ->and($banner->chips())->toBe([
            ['icon' => 'bi-lightning-charge-fill', 'text' => 'Fast onboarding'],
            ['icon' => 'bi-check-circle', 'text' => 'Certified'],
        ])
        ->and($banner->position)->toBe(3)
        ->and($banner->isPublished())->toBeTrue()
        ->and($banner->imageUrlFor('thumb'))->toContain('conversions');
});

it('validates the fields', function () {
    $this->actingAsAdmin()->post(route('admin.banners.store'), [
        'title' => ['en' => ''],
        'button_url' => 'javascript:alert(1)',
        'chip_1_icon' => 'Shield Check',
        'position' => '-1',
        'status' => 'archived',
        'image' => UploadedFile::fake()->create('virus.exe', 10),
    ])->assertSessionHasErrors(['title.en', 'button_url', 'chip_1_icon', 'position', 'status', 'image']);

    expect(Banner::count())->toBe(0);
});

it('updates a banner and removes its image on request', function () {
    Storage::fake('public');
    $banner = Banner::factory()->create();
    $banner->addMedia(UploadedFile::fake()->image('a.png'))->toMediaCollection('image');

    $this->actingAsAdmin()->get(route('admin.banners.edit', $banner))->assertOk()
        ->assertSee('form="banner-form"', false)->assertSee('id="banner-form"', false);

    $this->actingAsAdmin()->put(route('admin.banners.update', $banner), [
        'title' => ['en' => 'Renamed'], 'button_url' => '/services', 'status' => 'published', 'image_remove' => '1',
    ])->assertRedirect(route('admin.banners.edit', $banner));

    $banner->refresh();
    expect($banner->title)->toBe('Renamed')
        ->and($banner->button_url)->toBe('/services')
        ->and($banner->isPublished())->toBeTrue()
        ->and($banner->imageUrlFor())->toBeNull();
});

it('deletes banners, one or in bulk, and publishes in bulk', function () {
    [$first, $second, $third] = Banner::factory()->count(3)->create();

    $this->actingAsAdmin()->delete(route('admin.banners.destroy', $first))->assertRedirect(route('admin.banners.index'));
    expect(Banner::find($first->id))->toBeNull();

    $this->actingAsAdmin()->post(route('admin.banners.bulk'), ['action' => 'publish', 'ids' => [$second->id, $third->id]])
        ->assertSessionHas('success');
    expect(Banner::published()->count())->toBe(2);

    $this->actingAsAdmin()->post(route('admin.banners.bulk'), ['action' => 'delete', 'ids' => [$second->id]]);
    expect(Banner::pluck('id')->all())->toBe([$third->id]);

    $this->actingAsUserWith(['banners.view'])->post(route('admin.banners.bulk'), ['action' => 'delete', 'ids' => [$third->id]])
        ->assertForbidden();
});
