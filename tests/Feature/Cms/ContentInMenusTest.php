<?php

use Illuminate\Support\Facades\URL;
use Modules\Blog\Models\Post;
use Modules\Groups\Database\Seeders\GroupsDatabaseSeeder;
use Modules\Navigation\Models\NavigationGroup;
use Modules\Pages\Models\Page;

/**
 * Phase 6 "done when": posts and pages, in en/ru/ky, show on the frontend and in the link picker,
 * and menus link to them in the visitor's language.
 */
beforeEach(function () {
    config(['cms.locales' => ['en', 'ru', 'ky'], 'cms.default_locale' => 'en', 'app.url' => 'http://localhost']);
    URL::useOrigin('http://localhost');

    foreach (['groups', 'navigation', 'categories', 'blog', 'pages'] as $module) {
        $this->useModule($module);
    }
    $this->seed(GroupsDatabaseSeeder::class);
});

it('offers posts and pages in the navigation link picker', function () {
    $post = Post::factory()->published()->create(['title' => ['en' => 'Nomad games', 'ru' => 'Игры кочевников']]);
    $page = Page::factory()->published()->create(['title' => ['en' => 'Nomad camps'], 'slug' => 'camps']);

    $this->actingAsAdmin()->get(route('admin.navigation.links.create', NavigationGroup::create(['slug' => 'main', 'title' => ['en' => 'Main']])))
        ->assertOk()
        ->assertSee(Post::class)
        ->assertSee(Page::class);

    $this->actingAsAdmin()->getJson(route('admin.navigation.linkables', ['type' => Post::class, 'q' => 'кочевник']))
        ->assertOk()->assertJson([['id' => $post->id, 'url' => 'http://localhost/blog/'.$post->slug]]);
    $this->actingAsAdmin()->getJson(route('admin.navigation.linkables', ['type' => Page::class, 'q' => 'nomad']))
        ->assertOk()->assertJson([['id' => $page->id, 'url' => 'http://localhost/camps']]);
});

it('renders menu links to pages and posts in the visitor\'s language, marking the current one', function () {
    $page = Page::factory()->published()->create(['slug' => 'about', 'title' => ['en' => 'About', 'ru' => 'О нас', 'ky' => 'Биз жөнүндө']]);
    $menu = NavigationGroup::create(['slug' => 'header', 'title' => ['en' => 'Header']]);
    $menu->links()->create(['type' => 'model', 'linkable_type' => Page::class, 'linkable_id' => $page->id, 'title' => null, 'sort_order' => 1]);
    $menu->links()->create(['type' => 'route', 'route_name' => 'blog.index', 'title' => ['en' => 'Blog', 'ru' => 'Блог'], 'sort_order' => 2]);

    foreach (['' => 'About', '/ru' => 'О нас', '/ky' => 'Биз жөнүндө'] as $prefix => $title) {
        $this->get($prefix.'/about')
            ->assertOk()
            ->assertSee('<h1 class="mb-4">'.$title.'</h1>', false)
            ->assertSee('href="http://localhost'.$prefix.'/about"', false)
            ->assertSee('href="http://localhost'.$prefix.'/blog"', false)
            ->assertSee('active', false);
    }
});
