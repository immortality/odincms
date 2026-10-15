<?php

use App\Cms\Modules\ModuleManager;
use App\Cms\Widgets\WidgetRegistry;
use Illuminate\Validation\ValidationException;
use Modules\Sections\Database\Seeders\SectionsDatabaseSeeder;
use Modules\Sections\Widgets\MarqueeWidget;
use Modules\Sections\Widgets\WhyChooseUsWidget;
use Modules\Widgets\Database\Seeders\WidgetsDatabaseSeeder;
use Modules\Widgets\Models\WidgetArea;
use Modules\Widgets\Models\WidgetInstance;

beforeEach(function () {
    $this->useModule('sections');
});

/**
 * Full "Why choose us" settings, with overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function whyChooseUsSettings(array $overrides = []): array
{
    return [
        'slogan' => ['en' => 'Fast websites', 'ru' => 'Быстрые сайты'],
        'intro' => ['en' => 'Senior developers.'],
        'badge' => ['en' => 'Laravel experts'],
        'card_1_icon' => 'code-slash',
        'card_1_title' => ['en' => 'Custom Development', 'ru' => 'Разработка под задачу'],
        'card_1_text' => ['en' => 'Built around your workflow.'],
        'card_2_icon' => 'speedometer2',
        'card_2_title' => ['en' => 'Performance & SEO'],
        'card_2_text' => ['en' => 'Green Core Web Vitals.'],
        'bar_label' => ['en' => 'Projects launched on schedule'],
        'bar_value' => 97,
        'link_label' => ['en' => 'Start a project'],
        'link_url' => '/contact',
        ...$overrides,
    ];
}

it('registers both widgets while the module is enabled, and not after it is disabled', function () {
    expect(app(WidgetRegistry::class)->get('sections.why-choose-us'))->toBe(WhyChooseUsWidget::class)
        ->and(app(WidgetRegistry::class)->get('sections.marquee'))->toBe(MarqueeWidget::class);

    $this->useModule('widgets');
    $this->actingAsAdmin()->get(route('admin.widgets.index'))->assertOk()->assertSee(['Why choose us', 'Marquee']);

    // The next request registers only the widgets of the enabled modules (as ModuleServiceProvider does).
    app(ModuleManager::class)->disable('sections');
    $registry = new WidgetRegistry;
    foreach (app(ModuleManager::class)->enabledManifests() as $manifest) {
        foreach ($manifest->widgets() as $class) {
            $registry->register($class);
        }
    }

    expect($registry->has('sections.why-choose-us'))->toBeFalse()
        ->and($registry->has('sections.marquee'))->toBeFalse();
});

describe('Why choose us', function () {
    it('renders the heading, the cards, the badge, the progress bar and the button', function () {
        $html = (new WhyChooseUsWidget(whyChooseUsSettings()))->setTitle('Why choose us')->render()->render();

        expect($html)->toContain(
            '<span class="eyebrow">Why choose us</span>',
            '<h2 class="section-title">Fast websites</h2>',
            'Senior developers.',
            'Laravel experts',
            'bi bi-code-slash', 'Custom Development', 'Built around your workflow.',
            'bi bi-speedometer2', 'Performance &amp; SEO',
            'role="progressbar"', 'aria-valuenow="97"', 'aria-label="Projects launched on schedule"', '97%',
            'href="'.url('/contact').'"', 'Start a project',
        );
    });

    it('leaves out an empty card, bar, badge and button', function () {
        $html = (new WhyChooseUsWidget(whyChooseUsSettings([
            'card_2_title' => [], 'badge' => [], 'bar_label' => [], 'link_label' => [],
        ])))->render()->render();

        expect($html)->toContain('Custom Development')
            ->not->toContain('Performance')
            ->not->toContain('wcu-badge')
            ->not->toContain('progressbar')
            ->not->toContain('btn-lime');

        $html = (new WhyChooseUsWidget(whyChooseUsSettings(['card_1_title' => [], 'card_2_title' => []])))->render()->render();
        expect($html)->not->toContain('wcu-cards');
    });

    it('shows the visitor\'s language and falls back to the default one', function () {
        $this->useModule('widgets');
        $this->seed(WidgetsDatabaseSeeder::class);
        WidgetInstance::create([
            'area_id' => WidgetArea::where('slug', 'home')->sole()->id,
            'widget' => 'sections.why-choose-us', 'title' => ['en' => 'Why choose us', 'ru' => 'Почему мы'],
            'settings' => whyChooseUsSettings(), 'sort_order' => 1, 'is_active' => true,
        ]);

        $this->get('/ru')->assertOk()->assertSee(['Почему мы', 'Быстрые сайты', 'Разработка под задачу'])->assertDontSee('Fast websites');
        $this->get('/ky')->assertOk()->assertSee(['Why choose us', 'Fast websites', 'Custom Development']);
    });

    it('validates its settings', function () {
        $widget = new WhyChooseUsWidget;

        expect($widget->validate(whyChooseUsSettings(['bar_value' => '80'])))->toMatchArray(['bar_value' => 80]);
        expect($widget->validate(whyChooseUsSettings(['link_url' => 'https://example.com/brief'])))->toMatchArray(['link_url' => 'https://example.com/brief']);

        foreach ([
            ['bar_value' => '101'],
            ['card_1_icon' => 'skull'],
            ['link_url' => 'javascript:alert(1)'],
            ['link_url' => '//evil.example'],
            ['slogan' => ['ru' => 'Только по-русски']],
            ['badge' => ['en' => str_repeat('a', 41)]],
        ] as $invalid) {
            expect(fn () => $widget->validate(whyChooseUsSettings($invalid)))->toThrow(ValidationException::class);
        }
    });

    it('escapes HTML in its fields', function () {
        $html = (new WhyChooseUsWidget(whyChooseUsSettings([
            'slogan' => ['en' => '<script>alert(1)</script>'],
            'card_1_title' => ['en' => '<b>Bold</b>'],
        ])))->render()->render();

        expect($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;', '&lt;b&gt;Bold&lt;/b&gt;')
            ->not->toContain('<script>alert(1)')->not->toContain('<b>Bold</b>');
    });
});

describe('Marquee', function () {
    it('drops blank lines and outputs the words twice, the second copy hidden from screen readers', function () {
        $html = (new MarqueeWidget(['words' => ['en' => "Design\n\n  Development  \r\n \nSEO\n"], 'speed' => 30]))->render()->render();

        expect(substr_count($html, '<ul class="marquee-ribbon-list"'))->toBe(2)
            ->and(substr_count($html, '<ul class="marquee-ribbon-list" aria-hidden="true">'))->toBe(1)
            ->and(substr_count($html, '<li'))->toBe(6)
            ->and(substr_count($html, '>Development<'))->toBe(2)
            ->and($html)->toContain('--marquee-speed: 30s', 'is-tilted')->not->toContain('<li></li>');
    });

    it('renders nothing without words', function () {
        expect((new MarqueeWidget(['words' => ['en' => " \n \n"]]))->render())->toBe('')
            ->and((new MarqueeWidget)->render())->toBe('');
    });
});

describe('the seeder', function () {
    it('places both widgets at the end of the home area once', function () {
        $this->seed(WidgetsDatabaseSeeder::class);
        $home = WidgetArea::where('slug', 'home')->sole();
        WidgetInstance::create(['area_id' => $home->id, 'widget' => 'core.html', 'sort_order' => 5, 'is_active' => true]);

        $this->seed(SectionsDatabaseSeeder::class);
        $this->seed(SectionsDatabaseSeeder::class);

        expect($home->instances()->pluck('widget')->all())->toBe(['core.html', 'sections.why-choose-us', 'sections.marquee']);

        $why = WidgetInstance::where('widget', 'sections.why-choose-us')->sole();
        expect($why->getTranslation('title', 'ru'))->toBe('Почему мы')
            ->and((new WhyChooseUsWidget)->validate($why->settings))->toBeArray(); // the seeded settings are valid
    });

    it('does nothing when the home area is missing', function () {
        $this->seed(SectionsDatabaseSeeder::class);

        expect(WidgetInstance::count())->toBe(0);
    });
});
