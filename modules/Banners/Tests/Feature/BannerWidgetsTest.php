<?php

use App\Cms\Widgets\WidgetRegistry;
use Illuminate\Validation\ValidationException;
use Modules\Banners\Models\Banner;
use Modules\Banners\Widgets\HeroWidget;
use Modules\Banners\Widgets\SliderWidget;
use Modules\Widgets\Database\Seeders\WidgetsDatabaseSeeder;
use Modules\Widgets\Models\WidgetArea;
use Modules\Widgets\Models\WidgetInstance;

beforeEach(function () {
    $this->useModule('banners');
});

it('registers the two widgets', function () {
    expect(app(WidgetRegistry::class)->get('banners.hero'))->toBe(HeroWidget::class)
        ->and(app(WidgetRegistry::class)->get('banners.slider'))->toBe(SliderWidget::class);
});

describe('Hero banner widget', function () {
    it('shows the chosen banner with its badge, button and chips', function () {
        $banner = Banner::factory()->published()->create([
            'title' => ['en' => 'Vision for the'], 'highlight' => ['en' => 'next generation'],
            'button_label' => ['en' => 'Start now'], 'button_url' => '/contact',
            'badge_label' => ['en' => 'NEW'], 'badge_text' => ['en' => 'Audit is live'],
            'chip_1_text' => ['en' => 'Fast onboarding'], 'chip_1_icon' => 'lightning-charge-fill',
        ]);

        $html = (new HeroWidget(['banner' => (string) $banner->id]))->render()->render();

        expect($html)->toContain('<section class="hero">', '<h1 class="hero-title reveal"', 'Vision for the', '<span class="grad">next generation</span>')
            ->toContain('<span>NEW</span>', 'Audit is live', 'href="/contact"', 'Start now');
    });

    it('shows nothing for a draft or a missing banner', function () {
        $draft = Banner::factory()->create();

        expect((new HeroWidget(['banner' => (string) $draft->id]))->render())->toBe('')
            ->and((new HeroWidget(['banner' => '999']))->render())->toBe('');
    });

    it('needs a banner', function () {
        $banner = Banner::factory()->create();

        expect((new HeroWidget)->validate(['banner' => (string) $banner->id]))->toBe(['banner' => (string) $banner->id]);
        expect(fn () => (new HeroWidget)->validate(['banner' => '']))->toThrow(ValidationException::class);
        expect(fn () => (new HeroWidget)->validate(['banner' => '999']))->toThrow(ValidationException::class);
    });
});

describe('Banner slider widget', function () {
    it('shows the published chosen banners as slides in their position order', function () {
        $late = Banner::factory()->published()->create(['title' => ['en' => 'Late slide'], 'position' => 5]);
        $early = Banner::factory()->published()->create(['title' => ['en' => 'Early slide'], 'position' => 1]);
        $draft = Banner::factory()->create(['title' => ['en' => 'Draft slide']]);
        Banner::factory()->published()->create(['title' => ['en' => 'Not chosen']]);

        $html = (new SliderWidget(['banners' => [(string) $late->id, (string) $early->id, (string) $draft->id], 'interval' => 4, 'arrows' => true, 'dots' => true]))
            ->render()->render();

        expect($html)->toContain('carousel slide', 'data-bs-ride="carousel"', 'data-bs-interval="4000"', 'data-bs-slide="next"', 'data-bs-slide-to="1"')
            ->not->toContain('Draft slide')->not->toContain('Not chosen')->not->toContain('reveal')
            ->and(strpos($html, 'Early slide'))->toBeLessThan(strpos($html, 'Late slide'))
            ->and($html)->toContain('<h1 class="hero-title"', '<h2 class="hero-title"');
    });

    it('can turn off autoplay, arrows and dots', function () {
        [$a, $b] = Banner::factory()->published()->count(2)->create();

        $html = (new SliderWidget(['banners' => [$a->id, $b->id], 'interval' => 0, 'arrows' => false, 'dots' => false]))->render()->render();

        expect($html)->toContain('data-bs-interval="false"')->not->toContain('data-bs-ride')
            ->not->toContain('data-bs-slide=')->not->toContain('data-bs-slide-to');
    });

    it('shows a single published banner as a plain hero, and nothing without one', function () {
        $only = Banner::factory()->published()->create(['title' => ['en' => 'Only one']]);
        $draft = Banner::factory()->create();

        $html = (new SliderWidget(['banners' => [$only->id, $draft->id]]))->render()->render();
        expect($html)->toContain('Only one')->not->toContain('carousel');

        expect((new SliderWidget(['banners' => [$draft->id]]))->render())->toBe('');
    });

    it('validates its settings', function () {
        $banner = Banner::factory()->create();

        expect((new SliderWidget)->validate(['banners' => [(string) $banner->id], 'interval' => '5', 'dots' => '1']))
            ->toMatchArray(['banners' => [(string) $banner->id], 'interval' => 5, 'arrows' => false, 'dots' => true]);

        expect(fn () => (new SliderWidget)->validate(['banners' => [], 'interval' => '5']))->toThrow(ValidationException::class);
        expect(fn () => (new SliderWidget)->validate(['banners' => [(string) $banner->id], 'interval' => '100']))->toThrow(ValidationException::class);
    });
});

describe('home page', function () {
    beforeEach(function () {
        $this->useModule('widgets');
        $this->seed(WidgetsDatabaseSeeder::class);
    });

    it('shows the hero widget at the top, without the widget title', function () {
        $banner = Banner::factory()->published()->create(['title' => ['en' => 'Our own hero']]);
        WidgetInstance::create([
            'area_id' => WidgetArea::where('slug', 'hero')->sole()->id,
            'widget' => 'banners.hero', 'title' => ['en' => 'Hidden title'],
            'settings' => ['banner' => (string) $banner->id], 'sort_order' => 1, 'is_active' => true, 'cache_ttl' => null,
        ]);

        $this->get('/')->assertOk()
            ->assertSee('<section class="hero">', false)
            ->assertSee('Our own hero')
            ->assertDontSee('Hidden title'); // the hero area shows no widget titles
    });

    it('shows no hero while the "hero" area is empty', function () {
        $this->get('/')->assertOk()->assertDontSee('<section class="hero', false);
    });

    it('shows a saved banner change right away, even when the widget is cached', function () {
        $banner = Banner::factory()->published()->create(['title' => ['en' => 'Before']]);
        WidgetInstance::create([
            'area_id' => WidgetArea::where('slug', 'hero')->sole()->id,
            'widget' => 'banners.hero', 'settings' => ['banner' => (string) $banner->id], 'sort_order' => 1, 'is_active' => true, 'cache_ttl' => 3600,
        ]);

        $this->get('/')->assertSee('Before');
        $banner->update(['title' => ['en' => 'After']]);
        $this->get('/')->assertSee('After')->assertDontSee('Before');
    });
});
