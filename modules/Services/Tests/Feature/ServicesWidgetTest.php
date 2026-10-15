<?php

use App\Cms\Widgets\WidgetRegistry;
use Illuminate\Validation\ValidationException;
use Modules\Services\Models\Service;
use Modules\Services\Widgets\ServicesWidget;
use Modules\Widgets\Database\Seeders\WidgetsDatabaseSeeder;
use Modules\Widgets\Models\WidgetArea;
use Modules\Widgets\Models\WidgetInstance;

beforeEach(function () {
    $this->useModule('services');
});

it('is registered by the module', function () {
    expect(app(WidgetRegistry::class)->get('services.list'))->toBe(ServicesWidget::class);
});

it('shows the newest published services as cards, with the "All services" link', function () {
    Service::factory()->published()->create(['title' => ['en' => 'Old one'], 'created_at' => now()->subDays(3)]);
    Service::factory()->published()->create(['title' => ['en' => 'New one'], 'created_at' => now()->subDay()]);
    Service::factory()->create(['title' => ['en' => 'Draft one']]);

    $html = (new ServicesWidget(['limit' => 1, 'link' => true]))->render()->render();

    expect($html)->toContain('service-card', 'New one', 'href="'.route('services.index').'"')
        ->not->toContain('Old one')->not->toContain('Draft one');

    expect((new ServicesWidget(['link' => false]))->render()->render())->not->toContain('href="'.route('services.index').'"');
});

it('shows nothing without published services, and validates its settings', function () {
    expect((new ServicesWidget)->render())->toBe('');

    expect((new ServicesWidget)->validate(['limit' => '6', 'link' => '1']))->toMatchArray(['limit' => 6, 'link' => true]);
    expect(fn () => (new ServicesWidget)->validate(['limit' => '50']))->toThrow(ValidationException::class);
});

it('shows its heading: the title as the eyebrow, the slogan with highlights (escaped), the intro and the detail', function () {
    Service::factory()->published()->create();

    $html = (new ServicesWidget([
        'slogan' => ['en' => 'We do *world-class* <b>work</b>'],
        'intro' => ['en' => 'Our programs.'],
        'detail' => ['en' => '<p>Free <em>content</em></p>'],
    ]))->setTitle('What we offer')->render()->render();

    expect($html)->toContain(
        '<span class="eyebrow">What we offer</span>',
        'We do <span class="hl">world-class</span> &lt;b&gt;work&lt;/b&gt;',
        'Our programs.',
        '<p>Free <em>content</em></p>',
    );

    expect((new ServicesWidget)->render()->render())->not->toContain('section-head'); // no heading at all when empty
});

it('shows the widget title once, as the eyebrow, in the home page area', function () {
    $this->useModule('widgets');
    $this->seed(WidgetsDatabaseSeeder::class);
    Service::factory()->published()->create();

    WidgetInstance::create([
        'area_id' => WidgetArea::where('slug', 'home')->sole()->id,
        'widget' => 'services.list', 'title' => ['en' => 'What we offer'],
        'settings' => ['slogan' => ['en' => 'Big slogan']], 'sort_order' => 1, 'is_active' => true,
    ]);

    $html = $this->get('/')->assertOk()->getContent();
    expect(substr_count($html, 'What we offer'))->toBe(1)
        ->and($html)->toContain('<span class="eyebrow">What we offer</span>', '<h2 class="section-title">Big slogan</h2>');
});
