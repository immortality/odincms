<?php

use App\Cms\Modules\Contracts\WidgetInstanceCleaner;
use App\Cms\Widgets\WidgetRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Groups\Database\Seeders\GroupsDatabaseSeeder;
use Modules\Widgets\Database\Seeders\WidgetsDatabaseSeeder;
use Modules\Widgets\Models\WidgetArea;
use Modules\Widgets\Models\WidgetInstance;
use Modules\Widgets\Support\EloquentWidgetInstanceCleaner;
use Modules\Widgets\Support\WidgetRenderer;
use Tests\Fixtures\Widgets\BrokenWidget;
use Tests\Fixtures\Widgets\GreetingWidget;

beforeEach(function () {
    $this->useModule('groups');
    $this->useModule('widgets');
    $this->seed(GroupsDatabaseSeeder::class);
    $this->seed(WidgetsDatabaseSeeder::class);

    app(WidgetRegistry::class)->register(GreetingWidget::class);
    app(WidgetRegistry::class)->register(BrokenWidget::class);
    GreetingWidget::$renders = 0;

    $this->sidebar = WidgetArea::where('slug', 'sidebar')->sole();
    $this->home = WidgetArea::where('slug', 'home')->sole();
});

function placeWidget(WidgetArea $area, string $widget = 'test.greeting', array $attributes = []): WidgetInstance
{
    static $order = 0;

    return WidgetInstance::create(array_merge([
        'area_id' => $area->id, 'widget' => $widget, 'settings' => [], 'sort_order' => ++$order, 'is_active' => true, 'cache_ttl' => null,
    ], $attributes));
}

function areaHtml(string $slug): string
{
    return Blade::render("@widgetArea('{$slug}')");
}

describe('rendering', function () {
    it('renders the active widgets of an area in order, with their titles', function () {
        placeWidget($this->sidebar, attributes: ['settings' => ['name' => 'Bishkek'], 'title' => ['en' => 'Welcome']]);
        placeWidget($this->sidebar, attributes: ['settings' => ['name' => 'Osh']]);
        placeWidget($this->sidebar, attributes: ['settings' => ['name' => 'Hidden'], 'is_active' => false]);

        $html = areaHtml('sidebar');

        expect($html)->toContain('<div class="widget-area widget-area-sidebar">')
            ->toContain('<h2 class="widget-title h5">Welcome</h2>')
            ->and(strpos($html, 'Hello, Bishkek!'))->toBeLessThan(strpos($html, 'Hello, Osh!'))
            ->and($html)->not->toContain('Hidden');
    });

    it('renders nothing for an empty or unknown area', function () {
        expect(areaHtml('sidebar'))->toBe('')
            ->and(areaHtml('nope'))->toBe('');
    });

    it('skips widgets whose module is not enabled', function () {
        placeWidget($this->sidebar, 'blog.latest-posts');
        placeWidget($this->sidebar);

        expect(areaHtml('sidebar'))->toContain('Hello, World!')->not->toContain('latest');
    });

    it('skips a widget that fails, logs it, and still renders the others', function () {
        Log::spy();
        placeWidget($this->sidebar, 'test.broken');
        placeWidget($this->sidebar);

        expect(areaHtml('sidebar'))->toContain('Hello, World!');
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'Something went wrong'));
    });

    it('applies the visibility rules', function () {
        placeWidget($this->sidebar, attributes: ['settings' => ['name' => 'Guests'], 'visibility' => ['audience' => 'guests']]);
        placeWidget($this->sidebar, attributes: ['settings' => ['name' => 'Russian'], 'visibility' => ['locales' => ['ru']]]);

        expect(areaHtml('sidebar'))->toContain('Hello, Guests!')->not->toContain('Russian');
    });

    it('only uses the areas of the current theme', function () {
        $other = WidgetArea::create(['theme' => 'dark', 'slug' => 'sidebar', 'name' => ['en' => 'Dark sidebar']]);
        placeWidget($other, attributes: ['settings' => ['name' => 'Dark']]);

        expect(areaHtml('sidebar'))->toBe('');

        config(['cms.theme' => 'dark']);
        expect(areaHtml('sidebar'))->toContain('Hello, Dark!');
    });

    it('renders an area with a custom view', function () {
        placeWidget($this->sidebar);
        view()->addNamespace('test-widgets', __DIR__.'/views');

        expect(Blade::render("@widgetArea('sidebar', 'test-widgets::compact')"))->toContain('COMPACT: test.greeting');
    });

    it('renders nothing when the Widgets module is not loaded', function () {
        placeWidget($this->sidebar);
        app()->offsetUnset('cms.widgets');

        expect(Blade::render("<aside>@widgetArea('sidebar')</aside>"))->toBe('<aside></aside>');
    });
});

describe('caching', function () {
    it('caches the output for cache_ttl seconds, per language', function () {
        placeWidget($this->sidebar, attributes: ['cache_ttl' => 600]);

        areaHtml('sidebar');
        areaHtml('sidebar');
        expect(GreetingWidget::$renders)->toBe(1);

        app()->setLocale('ru');
        areaHtml('sidebar');
        expect(GreetingWidget::$renders)->toBe(2);
    });

    it('does not cache without a cache_ttl', function () {
        placeWidget($this->sidebar);

        areaHtml('sidebar');
        areaHtml('sidebar');
        expect(GreetingWidget::$renders)->toBe(2);
    });

    it('reads all the theme\'s areas with one query, then from the cache', function () {
        placeWidget($this->sidebar);
        placeWidget($this->home);

        DB::enableQueryLog();
        areaHtml('sidebar');
        areaHtml('home');
        areaHtml('hero');
        expect(collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'widget_'))->count())->toBe(2); // areas + instances

        DB::flushQueryLog();
        app()->forgetInstance(WidgetRenderer::class); // a new request: only the cache
        app()->forgetInstance('cms.widgets');
        expect(areaHtml('sidebar'))->toContain('Hello')
            ->and(collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'widget_'))->count())->toBe(0);
    });

    it('shows an instance added in code right away (the model flushes the cache)', function () {
        expect(areaHtml('sidebar'))->toBe('');

        placeWidget($this->sidebar, attributes: ['settings' => ['name' => 'New']]);

        expect(areaHtml('sidebar'))->toContain('Hello, New!');
    });

    it('shows a change right after saving in the admin', function () {
        $instance = placeWidget($this->sidebar, attributes: ['settings' => ['name' => 'Before'], 'cache_ttl' => 3600]);
        expect(areaHtml('sidebar'))->toContain('Hello, Before!');

        $this->actingAsAdmin()->put(route('admin.widgets.update', $instance), [
            'area_id' => $this->sidebar->id, 'settings' => ['name' => 'After'], 'is_active' => '1', 'cache_ttl' => '3600',
            'page_mode' => 'all', 'audience' => 'everyone',
        ])->assertSessionHasNoErrors();

        expect(areaHtml('sidebar'))->toContain('Hello, After!');
    });
});

describe('admin', function () {
    it('puts the title and the translatable settings under one set of language tabs', function () {
        $html = $this->actingAsAdmin()->get(route('admin.widgets.create', ['widget' => 'core.html', 'area' => $this->home->id]))
            ->assertOk()->getContent();

        expect(substr_count($html, 'role="tablist"'))->toBe(1)
            ->and($html)->toMatch('/name="title\[en\]".*name="settings\[content\]\[en\]".*name="title\[ru\]"/s');
    });

    it('shows the board with every area and the available widgets', function () {
        placeWidget($this->sidebar, attributes: ['title' => ['en' => 'My greeting']]);
        placeWidget($this->sidebar, 'blog.gone');

        $this->actingAsAdmin()->get(route('admin.widgets.index'))
            ->assertOk()
            ->assertSeeInOrder(['Hero section', 'Home page', 'Sidebar', 'My greeting', 'Not available'])
            ->assertSee("@widgetArea('sidebar')", false)
            ->assertSee('Text / HTML')
            ->assertSee('Greeting');
    });

    it('adds an HTML widget to an area; it shows on the site right away, purified', function () {
        $home = WidgetArea::where('slug', 'home')->sole();

        $this->actingAsAdmin()->get(route('admin.widgets.create', ['widget' => 'core.html', 'area' => $home->id]))
            ->assertOk()
            ->assertSee('name="settings[content][en]"', false);

        $this->actingAsAdmin()->post(route('admin.widgets.store'), [
            'widget' => 'core.html', 'area_id' => $home->id, 'is_active' => '1', 'cache_ttl' => '3600',
            'title' => ['en' => 'Announcement'],
            'settings' => ['content' => ['en' => '<p>Spring tours are open!</p><script>evil()</script>']],
            'page_mode' => 'all', 'audience' => 'everyone',
        ])->assertRedirect(route('admin.widgets.index'));

        $this->get('/')->assertOk()->assertSee('Announcement')->assertSee('<p>Spring tours are open!</p>', false)->assertDontSee('evil()');
    });

    it('validates the widget fields and the visibility rules', function (array $input, string $error) {
        $this->actingAsAdmin()->post(route('admin.widgets.store'), array_merge([
            'widget' => 'test.greeting', 'area_id' => $this->sidebar->id, 'settings' => ['name' => 'OK'],
            'page_mode' => 'all', 'audience' => 'everyone',
        ], $input))->assertSessionHasErrors($error);
    })->with([
        'widget field' => [['settings' => ['name' => str_repeat('x', 30)]], 'settings.name'],
        'missing required widget field' => [['settings' => ['name' => '']], 'settings.name'],
        'unknown area' => [['area_id' => 999999], 'area_id'],
        'pages without patterns' => [['page_mode' => 'only', 'patterns_text' => ''], 'patterns'],
        'groups without groups' => [['audience' => 'groups'], 'groups'],
        'unknown language' => [['locales' => ['de']], 'locales.0'],
    ]);

    it('stores the visibility rules', function () {
        $this->actingAsAdmin()->post(route('admin.widgets.store'), [
            'widget' => 'test.greeting', 'area_id' => $this->sidebar->id, 'settings' => ['name' => 'X'],
            'page_mode' => 'only', 'patterns_text' => "blog.*\n/\n", 'locales' => ['ru'], 'audience' => 'groups', 'groups' => ['admin'],
        ])->assertSessionHasNoErrors();

        // toEqual: MySQL stores JSON object keys in its own order.
        expect(WidgetInstance::sole()->visibility)->toEqual([
            'pages' => ['mode' => 'only', 'patterns' => ['blog.*', '/']],
            'locales' => ['ru'],
            'audience' => 'groups',
            'groups' => ['admin'],
        ]);
    });

    it('returns 404 for an unknown widget type', function () {
        $this->actingAsAdmin()->get(route('admin.widgets.create', ['widget' => 'nope.nothing']))->assertNotFound();
    });

    it('moves widgets within and between areas', function () {
        $a = placeWidget($this->sidebar);
        $b = placeWidget($this->sidebar);
        $c = placeWidget($this->home);

        $this->actingAsAdmin()->postJson(route('admin.widgets.reorder'), ['areas' => [
            $this->sidebar->id => [$c->id, $a->id],
            $this->home->id => [$b->id],
        ]])->assertOk();

        expect([$c->fresh()->area_id, $c->fresh()->sort_order])->toBe([$this->sidebar->id, 1])
            ->and($a->fresh()->sort_order)->toBe(2)
            ->and($b->fresh()->area_id)->toBe($this->home->id);
    });

    it('refuses an incomplete board', function () {
        $a = placeWidget($this->sidebar);
        placeWidget($this->sidebar);

        $this->actingAsAdmin()->postJson(route('admin.widgets.reorder'), ['areas' => [$this->sidebar->id => [$a->id]]])->assertStatus(422);
    });

    it('deletes a widget', function () {
        $instance = placeWidget($this->sidebar);

        $this->actingAsAdmin()->delete(route('admin.widgets.destroy', $instance))->assertRedirect(route('admin.widgets.index'));

        expect(WidgetInstance::count())->toBe(0);
    });

    it('manages the areas', function () {
        $this->actingAsAdmin()->post(route('admin.widgets.areas.store'), ['slug' => 'footer-4', 'name' => ['en' => 'Footer, column 4']])
            ->assertRedirect(route('admin.widgets.areas.index'));
        $this->actingAsAdmin()->post(route('admin.widgets.areas.store'), ['slug' => 'sidebar', 'name' => ['en' => 'Again']])
            ->assertSessionHasErrors('slug');

        $area = WidgetArea::where('slug', 'footer-4')->sole();
        expect($area->theme)->toBe('default');

        placeWidget($area);
        $this->actingAsAdmin()->delete(route('admin.widgets.areas.destroy', $area));
        expect(WidgetArea::where('slug', 'footer-4')->exists())->toBeFalse()
            ->and(WidgetInstance::count())->toBe(0);
    });

    it('needs the widgets permissions', function () {
        $instance = placeWidget($this->sidebar);

        $this->actingAsUserWith([])->get(route('admin.widgets.index'))->assertForbidden();

        $viewer = $this->actingAsUserWith(['widgets.view']);
        $viewer->get(route('admin.widgets.index'))->assertOk()->assertDontSee('Save order');
        $viewer->get(route('admin.widgets.create', ['widget' => 'core.html']))->assertForbidden();
        $viewer->put(route('admin.widgets.update', $instance), ['area_id' => $this->sidebar->id])->assertForbidden();
        $viewer->postJson(route('admin.widgets.reorder'), ['areas' => []])->assertForbidden();
        $viewer->delete(route('admin.widgets.destroy', $instance))->assertForbidden();
    });
});

it('deletes a module\'s widgets when the module is uninstalled', function () {
    placeWidget($this->sidebar, 'blog.latest-posts');
    placeWidget($this->sidebar, 'blogger.thing');
    placeWidget($this->sidebar);

    expect(app(WidgetInstanceCleaner::class))->toBeInstanceOf(EloquentWidgetInstanceCleaner::class);
    app(WidgetInstanceCleaner::class)->remove('blog');

    expect(WidgetInstance::orderBy('id')->pluck('widget')->all())->toBe(['blogger.thing', 'test.greeting']);
});

it('seeds the default theme\'s areas', function () {
    $this->seed(WidgetsDatabaseSeeder::class); // idempotent

    expect(WidgetArea::orderBy('id')->pluck('slug')->all())->toBe(['hero', 'home', 'sidebar']);
});

it('is the renderer behind the directive', function () {
    expect(app('cms.widgets'))->toBeInstanceOf(WidgetRenderer::class);
});

it('builds the home page from the "hero" and "home" areas, the sections in the admin\'s order', function () {
    $hero = WidgetArea::where('slug', 'hero')->sole();
    placeWidget($hero, 'core.html', ['title' => ['en' => 'Hero title'], 'settings' => ['content' => ['en' => '<p>Big hero</p>']]]);
    placeWidget($this->home, 'core.html', ['title' => ['en' => 'Second section'], 'settings' => ['content' => ['en' => '<p>Two</p>']], 'sort_order' => 20]);
    placeWidget($this->home, 'core.html', ['title' => ['en' => 'First section'], 'settings' => ['content' => ['en' => '<p>One</p>']], 'sort_order' => 10]);
    placeWidget($this->home, 'core.html', ['title' => [], 'settings' => ['content' => ['en' => '<p>Untitled</p>']], 'sort_order' => 30]);

    $this->get('/')->assertOk()
        ->assertSeeInOrder(['Big hero', 'First section', 'One', 'Second section', 'Two', 'Untitled'], false)
        ->assertDontSee('Hero title') // the hero area has no headings
        ->assertSee('<h2 class="section-title">First section</h2>', false)
        ->assertSee('home-section widget-core-html is-alt', false); // every other section on the light background
});
