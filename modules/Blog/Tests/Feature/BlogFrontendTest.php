<?php

use App\Cms\Settings\Settings;
use App\Cms\Support\LinkableRegistry;
use App\Cms\Widgets\WidgetRegistry;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\Blog\Models\Post;
use Modules\Blog\Widgets\LatestPostsWidget;
use Modules\Categories\Models\Category;
use Modules\Categories\Support\CategoryTypes;

beforeEach(function () {
    config(['cms.locales' => ['en', 'ru', 'ky'], 'cms.default_locale' => 'en', 'app.url' => 'http://localhost']);
    URL::useOrigin('http://localhost');

    $this->useModule('categories');
    $this->useModule('blog');
});

it('lists published posts only, newest first', function () {
    Post::factory()->published()->create(['title' => ['en' => 'Older post'], 'published_at' => now()->subDays(2)]);
    Post::factory()->published()->create(['title' => ['en' => 'Newer post'], 'published_at' => now()->subDay()]);
    Post::factory()->create(['title' => ['en' => 'Secret draft']]);
    Post::factory()->scheduled()->create(['title' => ['en' => 'Future post']]);

    $this->get('/blog')
        ->assertOk()
        ->assertSeeInOrder(['Newer post', 'Older post'])
        ->assertDontSee('Secret draft')
        ->assertDontSee('Future post');
});

it('paginates by the blog setting', function () {
    app(Settings::class)->set('blog.per_page', 2);
    Post::factory()->published()->count(3)->create();

    $this->get('/blog')->assertOk()->assertSee('http://localhost/blog?page=2', false);
    $this->get('/ru/blog')->assertOk()->assertSee('http://localhost/ru/blog?page=2', false);
});

it('shows a post with SEO tags, and 404s for drafts and unknown slugs', function () {
    $post = Post::factory()->published()->create([
        'title' => ['en' => 'Song-Kul in July'],
        'meta_description' => ['en' => 'Horses & yurts'],
        'body' => ['en' => '<p>Two days by the lake.</p>'],
    ]);

    $this->get('/blog/'.$post->slug)
        ->assertOk()
        ->assertSee('<title>Song-Kul in July | ', false)
        ->assertSee('<meta name="description" content="Horses &amp; yurts">', false)
        ->assertSee('<link rel="alternate" hreflang="ru" href="http://localhost/ru/blog/'.$post->slug.'">', false)
        ->assertSee('<p>Two days by the lake.</p>', false);

    $this->get('/blog/'.Post::factory()->create()->slug)->assertNotFound();
    $this->get('/blog/'.Post::factory()->scheduled()->create()->slug)->assertNotFound();
    $this->get('/blog/no-such-post')->assertNotFound();
});

it('previews drafts for those who may edit them', function () {
    $draft = Post::factory()->create(['title' => ['en' => 'Work in progress']]);

    $this->actingAsAdmin()->get('/blog/'.$draft->slug)
        ->assertOk()
        ->assertSee('Work in progress')
        ->assertSee(__('blog::front.draft'))
        ->assertSee('<meta name="robots" content="noindex">', false);
});

it('shows the posts of a category and its subcategories', function () {
    $asia = Category::factory()->create(['type' => 'blog', 'title' => ['en' => 'Asia'], 'slug' => 'asia']);
    $kg = Category::factory()->childOf($asia)->create(['title' => ['en' => 'Kyrgyzstan']]);
    $europe = Category::factory()->create(['type' => 'blog', 'slug' => 'europe']);
    Category::factory()->inactive()->create(['type' => 'blog', 'slug' => 'hidden']);

    Post::factory()->published()->create(['title' => ['en' => 'Ala-Archa hike'], 'category_id' => $kg->id]);
    Post::factory()->published()->create(['title' => ['en' => 'Paris weekend'], 'category_id' => $europe->id]);

    $this->get('/blog/category/asia')->assertOk()->assertSee('Ala-Archa hike')->assertDontSee('Paris weekend');
    $this->get('/blog/category/hidden')->assertNotFound();
    $this->get('/blog/category/nope')->assertNotFound();
});

it('shows posts in the visitor\'s language, falling back to the default one', function () {
    $post = Post::factory()->published()->create(['title' => ['en' => 'Mountains', 'ru' => 'Горы']]);
    $english = Post::factory()->published()->create(['title' => ['en' => 'Only in English']]);

    $this->get('/ru/blog/'.$post->slug)->assertOk()->assertSee('Горы')->assertSee('<html lang="ru">', false);
    $this->get('/ky/blog/'.$english->slug)->assertOk()->assertSee('Only in English');
    $this->get('/ru/blog')->assertSee('http://localhost/ru/blog/'.$post->slug, false);
});

it('registers the blog category type and makes posts linkable from menus', function () {
    $post = Post::factory()->published()->create(['title' => ['en' => 'Linked post']]);
    $category = Category::factory()->create(['type' => 'blog', 'slug' => 'news']);

    expect(app(CategoryTypes::class)->has('blog'))->toBeTrue()
        ->and($category->linkUrl())->toBe('http://localhost/blog/category/news')
        ->and(app(LinkableRegistry::class)->has(Post::class))->toBeTrue()
        ->and(Post::linkableSearch('linked')->pluck('id')->all())->toBe([$post->id])
        ->and($post->linkUrl())->toBe('http://localhost/blog/'.$post->slug);
});

describe('Latest posts widget', function () {
    it('is registered by the module', function () {
        expect(app(WidgetRegistry::class)->get('blog.latest-posts'))->toBe(LatestPostsWidget::class);
    });

    it('shows the newest posts, optionally of one category', function () {
        $news = Category::factory()->create(['type' => 'blog']);
        Post::factory()->published()->create(['title' => ['en' => 'First'], 'published_at' => now()->subDays(3)]);
        Post::factory()->published()->create(['title' => ['en' => 'Second'], 'published_at' => now()->subDays(2), 'category_id' => $news->id]);
        Post::factory()->published()->create(['title' => ['en' => 'Third'], 'published_at' => now()->subDay()]);
        Post::factory()->create(['title' => ['en' => 'Draft']]);

        $html = (new LatestPostsWidget(['limit' => 2]))->render()->render();
        expect($html)->toContain('Third', 'Second')->not->toContain('First')->not->toContain('Draft');

        $html = (new LatestPostsWidget(['category' => (string) $news->id]))->render()->render();
        expect($html)->toContain('Second')->not->toContain('Third');
    });

    it('shows the posts as news cards with a link to the blog in the "cards" layout', function () {
        $news = Category::factory()->create(['type' => 'blog']);
        Post::factory()->published()->create(['title' => ['en' => 'Card post'], 'category_id' => $news->id]);

        $html = (new LatestPostsWidget(['layout' => 'cards', 'show_date' => true]))->render()->render();
        expect($html)->toContain('blog-card', 'Card post', 'href="'.route('blog.index').'"');

        expect((new LatestPostsWidget)->render()->render())->toContain('Card post')->not->toContain('blog-card'); // "list" by default
    });

    it('gives the cards their own heading (title as the eyebrow, slogan, "View all posts") and a cover with the category', function () {
        $news = Category::factory()->create(['type' => 'blog', 'title' => ['en' => 'Trends']]);
        Post::factory()->published()->create(['title' => ['en' => 'No cover'], 'category_id' => $news->id]);

        $cards = new LatestPostsWidget(['layout' => 'cards', 'show_image' => true, 'slogan' => ['en' => 'Featured news & *insights*']]);
        $html = $cards->setTitle('Our blog update')->render()->render();

        expect($cards->ownsHeading())->toBeTrue()
            ->and((new LatestPostsWidget)->ownsHeading())->toBeFalse() // the list keeps the area's heading
            ->and($html)->toContain('<span class="eyebrow">Our blog update</span>', 'Featured news &amp; <span class="hl">insights</span>',
                'View all posts', 'blog-card-placeholder', '<span class="cat">Trends</span>');
    });

    it('validates its settings', function () {
        expect((new LatestPostsWidget)->validate(['limit' => '3', 'category' => '', 'show_image' => '1']))
            ->toMatchArray(['limit' => 3, 'show_image' => true, 'show_date' => false]);

        expect(fn () => (new LatestPostsWidget)->validate(['limit' => '50']))->toThrow(ValidationException::class);
    });
});
