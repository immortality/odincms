<?php

use App\Cms\Support\LinkableRegistry;
use Illuminate\Support\Facades\Route;
use Modules\Groups\Database\Seeders\GroupsDatabaseSeeder;
use Modules\Navigation\Models\NavigationGroup;
use Modules\Navigation\Models\NavigationLink;
use Tests\Fixtures\Navigation\LinkableModule;

beforeEach(function () {
    $this->useModule('groups');
    $this->useModule('navigation');
    $this->seed(GroupsDatabaseSeeder::class);

    Route::get('/about', fn () => 'about')->name('about');
    Route::get('/tours/{country}', fn () => 'tours')->name('tours.country');
    Route::getRoutes()->refreshNameLookups();

    $this->menu = NavigationGroup::create(['slug' => 'header', 'title' => ['en' => 'Header menu']]);
});

function linkInput(array $overrides = []): array
{
    return array_merge(['type' => 'url', 'url' => '/about', 'title' => ['en' => 'About'], 'is_active' => '1'], $overrides);
}

describe('menus', function () {
    it('lists the menus with their theme code and link counts', function () {
        $this->menu->links()->create(['type' => 'url', 'url' => '/a', 'title' => ['en' => 'A']]);

        $this->actingAsAdmin()->get(route('admin.navigation.index'))
            ->assertOk()
            ->assertSee('Header menu')
            ->assertSee("@navigation('header')", false);
    });

    it('creates, renames and deletes a menu with its links', function () {
        $this->actingAsAdmin()->post(route('admin.navigation.store'), ['slug' => 'sidebar', 'title' => ['en' => 'Sidebar']])
            ->assertRedirect(route('admin.navigation.links.index', 'sidebar'));

        $sidebar = NavigationGroup::where('slug', 'sidebar')->sole();
        $sidebar->links()->create(['type' => 'url', 'url' => '/a', 'title' => ['en' => 'A']]);

        $this->put(route('admin.navigation.update', $sidebar), ['slug' => 'aside', 'title' => ['en' => 'Aside']])
            ->assertRedirect(route('admin.navigation.edit', 'aside'));

        $this->delete(route('admin.navigation.destroy', 'aside'))->assertRedirect(route('admin.navigation.index'));
        expect(NavigationGroup::where('slug', 'aside')->exists())->toBeFalse()
            ->and(NavigationLink::count())->toBe(0);
    });

    it('validates the menu identifier', function (string $slug) {
        $this->actingAsAdmin()->post(route('admin.navigation.store'), ['slug' => $slug, 'title' => ['en' => 'X']])
            ->assertSessionHasErrors('slug');
    })->with(['Header', 'two words', 'header', 'create', 'linkables']);
});

describe('links', function () {
    it('shows the tree, including broken links', function () {
        $tours = $this->menu->links()->create(['type' => 'url', 'url' => '/tours', 'title' => ['en' => 'Tours']]);
        $this->menu->links()->create(['type' => 'url', 'url' => '/tours/kg', 'title' => ['en' => 'Kyrgyzstan'], 'parent_id' => $tours->id]);
        $this->menu->links()->create(['type' => 'route', 'route_name' => 'gone', 'title' => ['en' => 'Old page']]);

        $this->actingAsAdmin()->get(route('admin.navigation.links.index', $this->menu))
            ->assertOk()
            ->assertSeeInOrder(['Tours', '/tours', 'Kyrgyzstan', 'Old page', 'Broken'])
            ->assertSee('Save order');
    });

    it('adds an address link at the end of its level', function () {
        $this->menu->links()->create(['type' => 'url', 'url' => '/a', 'title' => ['en' => 'A'], 'sort_order' => 5]);

        $this->actingAsAdmin()->post(route('admin.navigation.links.store', $this->menu), linkInput([
            'title' => ['en' => 'Contacts', 'ru' => 'Контакты'], 'url' => '#contacts', 'css_class' => 'btn-cta', 'open_new_tab' => '1',
            'visibility' => ['@guests', 'admin'],
        ]))->assertRedirect(route('admin.navigation.links.index', $this->menu));

        $link = NavigationLink::where('url', '#contacts')->sole();
        expect($link->getTranslations('title'))->toBe(['en' => 'Contacts', 'ru' => 'Контакты'])
            ->and($link->sort_order)->toBe(6)
            ->and($link->open_new_tab)->toBeTrue()
            ->and($link->visibility)->toBe(['@guests', 'admin']);
    });

    it('adds a site section link', function () {
        $this->actingAsAdmin()->post(route('admin.navigation.links.store', $this->menu), linkInput([
            'type' => 'route', 'url' => 'ignored', 'route_name' => 'about',
        ]))->assertSessionHasNoErrors();

        $link = NavigationLink::where('route_name', 'about')->sole();
        expect($link->url)->toBeNull(); // only the fields of the chosen type are stored
    });

    it('adds a site address link, like about-us or contact', function (string $typed, string $stored) {
        $this->actingAsAdmin()->post(route('admin.navigation.links.store', $this->menu), linkInput([
            'type' => 'path', 'url' => 'ignored', 'path' => $typed,
        ]))->assertSessionHasNoErrors();

        expect(NavigationLink::where('type', 'path')->sole()->url)->toBe($stored);
    })->with([
        'plain' => ['about-us', 'about-us'],
        'slashes and spaces' => [' /contact/ ', 'contact'],
        'nested, with an anchor' => ['about-us/team#leaders', 'about-us/team#leaders'],
        'empty: the home page' => ['', '/'],
    ]);

    it('offers the content as a list to choose from, and no sections that need parameters', function () {
        app(LinkableRegistry::class)->register(LinkableModule::class, 'Content');
        LinkableModule::create(['slug' => 'silk-road', 'name' => 'Silk Road', 'version' => '1.0.0']);

        $this->actingAsAdmin()->get(route('admin.navigation.links.create', $this->menu))
            ->assertOk()
            ->assertSee('<option value="'.LinkableModule::where('slug', 'silk-road')->value('id').'" >Silk Road</option>', false)
            ->assertSee('about (/about)')
            ->assertDontSee('tours.country');
    });

    it('adds a content link, which may borrow the content title', function () {
        app(LinkableRegistry::class)->register(LinkableModule::class, 'Content');
        $content = LinkableModule::create(['slug' => 'silk-road', 'name' => 'Silk Road', 'version' => '1.0.0']);

        $this->actingAsAdmin()->post(route('admin.navigation.links.store', $this->menu), linkInput([
            'type' => 'model', 'title' => [], 'linkable_type' => LinkableModule::class, 'linkable_id' => $content->id,
        ]))->assertSessionHasNoErrors();

        expect(NavigationLink::where('type', 'model')->sole()->linkable_id)->toBe($content->id);
    });

    it('validates each type', function (array $input, string $error) {
        app(LinkableRegistry::class)->register(LinkableModule::class, 'Content');

        $this->actingAsAdmin()->post(route('admin.navigation.links.store', $this->menu), linkInput($input))
            ->assertSessionHasErrors($error);
    })->with([
        'missing title' => [['title' => []], 'title.en'],
        'bad address' => [['url' => 'javascript:alert(1)'], 'url'],
        'missing address' => [['url' => ''], 'url'],
        'unknown page' => [['type' => 'route', 'route_name' => 'admin.dashboard'], 'route_name'],
        'section that needs parameters' => [['type' => 'route', 'route_name' => 'tours.country'], 'route_name'],
        'external address as site address' => [['type' => 'path', 'path' => 'https://example.com'], 'path'],
        'site address with spaces' => [['type' => 'path', 'path' => 'about us'], 'path'],
        'unregistered content type' => [['type' => 'model', 'linkable_type' => 'App\\Models\\User', 'linkable_id' => 1], 'linkable_type'],
        'missing content' => [['type' => 'model', 'linkable_type' => LinkableModule::class, 'linkable_id' => 999999], 'linkable_id'],
        'bad css class' => [['css_class' => 'x" onclick="evil'], 'css_class'],
        'unknown visibility' => [['visibility' => ['@robots']], 'visibility.0'],
    ]);

    it('does not offer admin or login pages as link targets', function () {
        $this->actingAsAdmin()->get(route('admin.navigation.links.create', $this->menu))
            ->assertOk()
            ->assertSee('about (/about)')
            ->assertDontSee('admin.dashboard')
            ->assertDontSee('login (/');
    });

    it('cannot put a link below itself or its own children', function () {
        $parent = $this->menu->links()->create(['type' => 'url', 'url' => '/p', 'title' => ['en' => 'P']]);
        $child = $this->menu->links()->create(['type' => 'url', 'url' => '/c', 'title' => ['en' => 'C'], 'parent_id' => $parent->id]);

        $this->actingAsAdmin()->put(route('admin.navigation.links.update', [$this->menu, $parent]), linkInput(['parent_id' => $child->id]))
            ->assertSessionHasErrors('parent_id');
    });

    it('deletes a link with its children', function () {
        $parent = $this->menu->links()->create(['type' => 'url', 'url' => '/p', 'title' => ['en' => 'P']]);
        $this->menu->links()->create(['type' => 'url', 'url' => '/c', 'title' => ['en' => 'C'], 'parent_id' => $parent->id]);

        $this->actingAsAdmin()->delete(route('admin.navigation.links.destroy', [$this->menu, $parent]));

        expect(NavigationLink::count())->toBe(0);
    });

    it('only finds links of the menu in the URL', function () {
        $other = NavigationGroup::create(['slug' => 'footer', 'title' => ['en' => 'Footer']]);
        $link = $other->links()->create(['type' => 'url', 'url' => '/x', 'title' => ['en' => 'X']]);

        $this->actingAsAdmin()->get(route('admin.navigation.links.edit', [$this->menu, $link]))->assertNotFound();
    });
});

describe('reorder', function () {
    beforeEach(function () {
        $this->a = $this->menu->links()->create(['type' => 'url', 'url' => '/a', 'title' => ['en' => 'A'], 'sort_order' => 1]);
        $this->b = $this->menu->links()->create(['type' => 'url', 'url' => '/b', 'title' => ['en' => 'B'], 'sort_order' => 2]);
        $this->c = $this->menu->links()->create(['type' => 'url', 'url' => '/c', 'title' => ['en' => 'C'], 'sort_order' => 3]);
    });

    it('saves a new order and nesting', function () {
        $this->actingAsAdmin()->postJson(route('admin.navigation.links.reorder', $this->menu), ['items' => [
            ['id' => $this->c->id, 'parent_id' => null],
            ['id' => $this->a->id, 'parent_id' => null],
            ['id' => $this->b->id, 'parent_id' => $this->a->id],
        ]])->assertOk()->assertJson(['message' => 'The order has been saved.']);

        expect([$this->c->fresh()->sort_order, $this->a->fresh()->sort_order])->toBe([1, 2])
            ->and($this->b->fresh()->parent_id)->toBe($this->a->id)
            ->and($this->b->fresh()->sort_order)->toBe(1);
    });

    it('refuses an incomplete list, a loop or too many levels', function (Closure $items) {
        $this->actingAsAdmin()->postJson(route('admin.navigation.links.reorder', $this->menu), ['items' => $items($this->a, $this->b, $this->c)])
            ->assertStatus(422);

        expect($this->a->fresh()->parent_id)->toBeNull();
    })->with([
        'missing link' => [fn ($a, $b, $c) => [['id' => $a->id, 'parent_id' => null], ['id' => $b->id, 'parent_id' => null]]],
        'loop' => [fn ($a, $b, $c) => [['id' => $a->id, 'parent_id' => $b->id], ['id' => $b->id, 'parent_id' => $a->id], ['id' => $c->id, 'parent_id' => null]]],
        'foreign parent' => [fn ($a, $b, $c) => [['id' => $a->id, 'parent_id' => 999999], ['id' => $b->id, 'parent_id' => null], ['id' => $c->id, 'parent_id' => null]]],
    ]);

    it('allows at most three levels', function () {
        $d = $this->menu->links()->create(['type' => 'url', 'url' => '/d', 'title' => ['en' => 'D']]);

        $this->actingAsAdmin()->postJson(route('admin.navigation.links.reorder', $this->menu), ['items' => [
            ['id' => $this->a->id, 'parent_id' => null],
            ['id' => $this->b->id, 'parent_id' => $this->a->id],
            ['id' => $this->c->id, 'parent_id' => $this->b->id],
            ['id' => $d->id, 'parent_id' => $this->c->id],
        ]])->assertStatus(422)->assertJson(['message' => 'Maximum depth: 3 levels.']);
    });
});

it('searches content for the link picker', function () {
    app(LinkableRegistry::class)->register(LinkableModule::class, 'Content');
    LinkableModule::create(['slug' => 'silk-road', 'name' => 'Silk Road', 'version' => '1.0.0']);
    LinkableModule::create(['slug' => 'pamir', 'name' => 'Pamir Highway', 'version' => '1.0.0']);

    $this->actingAsAdmin()->getJson(route('admin.navigation.linkables', ['type' => LinkableModule::class, 'q' => 'silk']))
        ->assertOk()
        ->assertExactJson([['id' => LinkableModule::where('slug', 'silk-road')->value('id'), 'label' => 'Silk Road', 'url' => url('/content/silk-road')]]);

    $this->getJson(route('admin.navigation.linkables', ['type' => 'App\\Models\\User']))->assertStatus(422);
});

it('needs the navigation permissions', function () {
    $link = $this->menu->links()->create(['type' => 'url', 'url' => '/a', 'title' => ['en' => 'A']]);

    $this->actingAsUserWith([])->get(route('admin.navigation.index'))->assertForbidden();

    $viewer = $this->actingAsUserWith(['navigation.view']);
    $viewer->get(route('admin.navigation.links.index', $this->menu))->assertOk()->assertDontSee('Save order');
    $viewer->post(route('admin.navigation.links.store', $this->menu), linkInput())->assertForbidden();
    $viewer->put(route('admin.navigation.links.update', [$this->menu, $link]), linkInput())->assertForbidden();
    $viewer->postJson(route('admin.navigation.links.reorder', $this->menu), ['items' => [['id' => $link->id]]])->assertForbidden();
    $viewer->delete(route('admin.navigation.links.destroy', [$this->menu, $link]))->assertForbidden();
    $viewer->post(route('admin.navigation.store'), ['slug' => 'x', 'title' => ['en' => 'X']])->assertForbidden();
});

it('puts Save and Delete in the page head of the menu and link forms', function () {
    $link = $this->menu->links()->create(['type' => 'url', 'url' => '/a', 'title' => ['en' => 'A']]);

    $this->actingAsAdmin()->get(route('admin.navigation.edit', $this->menu))
        ->assertOk()
        ->assertSee('form="menu-form"', false)->assertSee('id="menu-form"', false)
        ->assertSee('value="DELETE"', false);

    $this->actingAsAdmin()->get(route('admin.navigation.links.edit', [$this->menu, $link]))
        ->assertOk()
        ->assertSee('form="link-form"', false)->assertSee('id="link-form"', false)
        ->assertSee('value="DELETE"', false);

    // New items have nothing to delete; editors without navigation.delete get no Delete button.
    $this->actingAsAdmin()->get(route('admin.navigation.links.create', $this->menu))->assertDontSee('value="DELETE"', false);
    $this->actingAsUserWith(['navigation.view', 'navigation.edit'])->get(route('admin.navigation.links.edit', [$this->menu, $link]))
        ->assertOk()
        ->assertDontSee('value="DELETE"', false);
});
