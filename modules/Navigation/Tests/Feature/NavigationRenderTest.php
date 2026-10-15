<?php

use App\Cms\Support\LinkableRegistry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Modules\Groups\Database\Seeders\GroupsDatabaseSeeder;
use Modules\Navigation\Database\Seeders\NavigationDatabaseSeeder;
use Modules\Navigation\Models\NavigationGroup;
use Modules\Navigation\Models\NavigationLink;
use Modules\Navigation\Support\NavigationRenderer;
use Tests\Fixtures\Navigation\LinkableModule;

beforeEach(function () {
    $this->useModule('groups');
    $this->useModule('navigation');
    $this->seed(GroupsDatabaseSeeder::class);

    Route::get('/about', fn () => 'about')->name('about');
    Route::get('/tours/{country}', fn () => 'tours')->name('tours.country');
    Route::getRoutes()->refreshNameLookups();

    $this->menu = NavigationGroup::create(['slug' => 'header', 'title' => ['en' => 'Header']]);
    $this->renderer = app(NavigationRenderer::class);
});

function navLink(NavigationGroup $menu, array $attributes): NavigationLink
{
    static $order = 0;

    return $menu->links()->create(array_merge(['type' => 'url', 'url' => '/x', 'title' => ['en' => 'Link'], 'sort_order' => ++$order], $attributes));
}

/**
 * @return list<string> "Label → url" (children indented with "  ")
 */
function navLabels(array $items, string $indent = ''): array
{
    $lines = [];
    foreach ($items as $item) {
        $lines[] = $indent.$item['label'].' → '.$item['url'];
        $lines = [...$lines, ...navLabels($item['children'], $indent.'  ')];
    }

    return $lines;
}

describe('link types', function () {
    it('renders address, route and content links in order', function () {
        app(LinkableRegistry::class)->register(LinkableModule::class, 'Content');
        $content = LinkableModule::create(['slug' => 'silk-road', 'name' => 'Silk Road', 'version' => '1.0.0']);

        navLink($this->menu, ['title' => ['en' => 'Docs'], 'url' => 'https://example.com/docs']);
        navLink($this->menu, ['title' => ['en' => 'About'], 'type' => 'route', 'url' => null, 'route_name' => 'about']);
        navLink($this->menu, ['title' => ['en' => 'Kyrgyzstan'], 'type' => 'route', 'url' => null, 'route_name' => 'tours.country', 'route_parameters' => ['country' => 'kg']]);
        navLink($this->menu, ['title' => null, 'type' => 'model', 'url' => null, 'linkable_type' => LinkableModule::class, 'linkable_id' => $content->id]);

        expect(navLabels($this->renderer->items('header', null)))->toBe([
            'Docs → https://example.com/docs',
            'About → '.url('/about'),
            'Kyrgyzstan → '.url('/tours/kg'),
            'Silk Road → '.url('/content/silk-road'), // no title: the content's own label
        ]);
    });

    it('renders site addresses in the visitor\'s language', function () {
        config(['cms.locales' => ['en', 'ru'], 'cms.default_locale' => 'en']);
        navLink($this->menu, ['title' => ['en' => 'Contact'], 'type' => 'path', 'url' => 'contact']);
        navLink($this->menu, ['title' => ['en' => 'Home'], 'type' => 'path', 'url' => '/']);

        $root = url('/');
        expect(navLabels($this->renderer->items('header', null)))->toBe(['Contact → '.$root.'/contact', 'Home → '.$root]);

        app()->setLocale('ru');
        expect(array_column($this->renderer->items('header', null), 'url'))->toBe([$root.'/ru/contact', $root.'/ru']);
    });

    it('follows a content item when its URL changes', function () {
        app(LinkableRegistry::class)->register(LinkableModule::class, 'Content');
        $content = LinkableModule::create(['slug' => 'old-slug', 'name' => 'Tour', 'version' => '1.0.0']);
        navLink($this->menu, ['type' => 'model', 'url' => null, 'linkable_type' => LinkableModule::class, 'linkable_id' => $content->id]);

        expect($this->renderer->items('header', null)[0]['url'])->toBe(url('/content/old-slug'));

        $content->update(['slug' => 'new-slug']); // clears the cached menus

        expect($this->renderer->items('header', null)[0]['url'])->toBe(url('/content/new-slug'));
    });

    it('skips links whose route, parameters or content no longer exist', function () {
        app(LinkableRegistry::class)->register(LinkableModule::class, 'Content');
        navLink($this->menu, ['title' => ['en' => 'Kept']]);
        navLink($this->menu, ['type' => 'route', 'url' => null, 'route_name' => 'gone.route']);
        navLink($this->menu, ['type' => 'route', 'url' => null, 'route_name' => 'tours.country']); // missing parameter
        navLink($this->menu, ['type' => 'model', 'url' => null, 'linkable_type' => LinkableModule::class, 'linkable_id' => 999999]);

        expect(navLabels($this->renderer->items('header', null)))->toBe(['Kept → /x']);
    });

    it('skips content from a module that is not enabled (not registered)', function () {
        $content = LinkableModule::create(['slug' => 'x', 'name' => 'X', 'version' => '1.0.0']);
        navLink($this->menu, ['type' => 'model', 'url' => null, 'linkable_type' => LinkableModule::class, 'linkable_id' => $content->id]);

        expect($this->renderer->items('header', null))->toBe([]);
    });

    it('returns nothing for an unknown menu', function () {
        expect($this->renderer->items('nope', null))->toBe([]);
    });
});

describe('tree', function () {
    it('nests links and hides the children of hidden links', function () {
        $tours = navLink($this->menu, ['title' => ['en' => 'Tours'], 'url' => '/tours']);
        navLink($this->menu, ['title' => ['en' => 'Hiking'], 'url' => '/tours/hiking', 'parent_id' => $tours->id]);
        $hidden = navLink($this->menu, ['title' => ['en' => 'Hidden'], 'url' => '/hidden', 'is_active' => false]);
        navLink($this->menu, ['title' => ['en' => 'Under hidden'], 'url' => '/under', 'parent_id' => $hidden->id]);

        expect(navLabels($this->renderer->items('header', null)))->toBe(['Tours → /tours', '  Hiking → /tours/hiking']);
    });

    it('marks the current page and its parents as active', function () {
        $tours = navLink($this->menu, ['title' => ['en' => 'Tours'], 'type' => 'route', 'url' => null, 'route_name' => 'about']);
        navLink($this->menu, ['title' => ['en' => 'KG'], 'type' => 'route', 'url' => null, 'route_name' => 'tours.country', 'route_parameters' => ['country' => 'kg'], 'parent_id' => $tours->id]);
        navLink($this->menu, ['title' => ['en' => 'Other'], 'url' => '/other']);

        $items = $this->renderer->items('header', null, Request::create('/tours/kg'));

        expect($items[0]['active'])->toBeTrue()
            ->and($items[0]['children'][0]['active'])->toBeTrue()
            ->and($items[1]['active'])->toBeFalse();
    });

    it('marks the home link as active on the home page, but not anchors', function () {
        navLink($this->menu, ['title' => ['en' => 'Home'], 'type' => 'route', 'url' => null, 'route_name' => 'home']);
        navLink($this->menu, ['title' => ['en' => 'Contacts'], 'url' => '#contacts']);

        $items = $this->renderer->items('header', null, Request::create('/'));

        expect(array_column($items, 'active'))->toBe([true, false]);
    });

    it('uses the title in the current language', function () {
        navLink($this->menu, ['title' => ['en' => 'Tours', 'ru' => 'Туры']]);

        app()->setLocale('ru');
        expect($this->renderer->items('header', null)[0]['label'])->toBe('Туры');

        app()->setLocale('ky');
        expect($this->renderer->items('header', null)[0]['label'])->toBe('Tours');
    });
});

describe('visibility', function () {
    beforeEach(function () {
        navLink($this->menu, ['title' => ['en' => 'Everyone']]);
        navLink($this->menu, ['title' => ['en' => 'Sign in'], 'visibility' => [NavigationLink::GUESTS]]);
        navLink($this->menu, ['title' => ['en' => 'My account'], 'visibility' => [NavigationLink::MEMBERS]]);
        navLink($this->menu, ['title' => ['en' => 'Staff'], 'visibility' => ['admin']]);
    });

    it('shows guests the guest links', function () {
        expect(array_column($this->renderer->items('header', null), 'label'))->toBe(['Everyone', 'Sign in']);
    });

    it('shows members the member links', function () {
        $member = User::factory()->create()->assignRole('user');

        expect(array_column($this->renderer->items('header', $member), 'label'))->toBe(['Everyone', 'My account']);
    });

    it('shows group links to members of that group', function () {
        $admin = User::factory()->create()->assignRole('admin');

        expect(array_column($this->renderer->items('header', $admin), 'label'))->toBe(['Everyone', 'My account', 'Staff']);
    });
});

describe('caching', function () {
    it('caches the menu until a link changes', function () {
        $link = navLink($this->menu, ['title' => ['en' => 'Before']]);
        $this->renderer->items('header', null);

        NavigationLink::whereKey($link->id)->update(['url' => '/changed-quietly']);
        expect($this->renderer->items('header', null)[0]['url'])->toBe('/x');

        $this->actingAsAdmin()->put(route('admin.navigation.links.update', [$this->menu, $link]), [
            'type' => 'url', 'url' => '/after', 'title' => ['en' => 'After'], 'is_active' => '1',
        ]);
        expect($this->renderer->items('header', null)[0]['url'])->toBe('/after');
    });
});

describe('the @navigation directive', function () {
    it('renders the menu with the default Bootstrap markup', function () {
        $tours = navLink($this->menu, ['title' => ['en' => 'Tours'], 'url' => '/tours', 'css_class' => 'highlight']);
        navLink($this->menu, ['title' => ['en' => 'Hiking'], 'url' => 'https://hiking.example', 'parent_id' => $tours->id, 'open_new_tab' => true]);

        $html = Blade::render("@navigation('header')");

        expect($html)->toContain('<ul class="navbar-nav navigation-header">')
            ->toContain('nav-item dropdown highlight')
            ->toContain('href="https://hiking.example"')
            ->toContain('target="_blank" rel="noopener"');
    });

    it('renders a menu with a custom view', function () {
        navLink($this->menu, ['title' => ['en' => 'Tours'], 'url' => '/tours']);
        view()->addNamespace('test-nav', __DIR__.'/views');

        expect(Blade::render("@navigation('header', 'test-nav::footer')"))->toContain('FOOTER: Tours=/tours');
    });

    it('renders nothing when the Navigation module is not loaded', function () {
        app()->offsetUnset('cms.navigation');

        expect(Blade::render("<nav>@navigation('header')</nav>"))->toBe('<nav></nav>');
    });
});

it('seeds the header and footer menus with a Home link', function () {
    NavigationGroup::query()->delete();

    $this->seed(NavigationDatabaseSeeder::class);
    $this->seed(NavigationDatabaseSeeder::class); // idempotent

    expect(NavigationGroup::orderBy('id')->pluck('slug')->all())->toBe(['header', 'footer'])
        ->and(navLabels($this->renderer->items('header', null)))->toBe(['Home → '.url('/')]);
});
