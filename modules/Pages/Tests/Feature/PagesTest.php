<?php

use App\Cms\Support\LinkableRegistry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Pages\Models\Page;

beforeEach(function () {
    config(['cms.locales' => ['en', 'ru', 'ky'], 'cms.default_locale' => 'en', 'app.url' => 'http://localhost']);
    URL::useOrigin('http://localhost');

    $this->useModule('pages');
});

describe('admin', function () {
    it('sends guests to the login page and forbids users without permission', function () {
        $this->get(route('admin.pages.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.pages.index'))->assertForbidden();
        $this->actingAsUserWith(['pages.view'])->get(route('admin.pages.index'))->assertOk();
    });

    it('shows the tree in order, each subpage below its parent', function () {
        $about = Page::factory()->published()->create(['title' => ['en' => 'About us']]);
        Page::factory()->childOf($about)->create(['title' => ['en' => 'Our team']]);
        Page::factory()->create(['title' => ['en' => 'Contacts']]);

        $this->actingAsAdmin()->get(route('admin.pages.index'))->assertOk()
            ->assertSeeInOrder(['About us', 'Our team', 'Contacts'])
            ->assertSee('data-tree-search', false); // search and the status filter run in the browser
    });

    it('creates a page and a subpage with nested paths', function () {
        $this->actingAsAdmin()->get(route('admin.pages.create'))->assertOk()->assertSee('With sidebar');

        $this->actingAsAdmin()->post(route('admin.pages.store'), [
            'title' => ['en' => 'About us', 'ru' => 'О нас'],
            'body' => ['en' => '<p>Hi</p><script>x()</script>'],
            'layout' => 'sidebar',
            'status' => 'published',
        ])->assertRedirect()->assertSessionHas('success');

        $about = Page::sole();
        expect($about->path)->toBe('about-us')
            ->and($about->getTranslation('body', 'en'))->toBe('<p>Hi</p>')
            ->and($about->layout)->toBe('sidebar');

        $this->actingAsAdmin()->post(route('admin.pages.store'), [
            'title' => ['en' => 'Team'], 'slug' => 'team', 'parent_id' => $about->id, 'layout' => 'default', 'status' => 'draft',
        ])->assertSessionHasNoErrors();

        expect(Page::where('slug', 'team')->value('path'))->toBe('about-us/team');
    });

    it('keeps slugs unique per level and out of the site\'s own addresses', function () {
        $about = Page::factory()->create(['slug' => 'about']);
        Page::factory()->childOf($about)->create(['slug' => 'team']);
        $post = fn (array $data) => $this->actingAsAdmin()->post(route('admin.pages.store'), [
            'title' => ['en' => 'X'], 'layout' => 'default', 'status' => 'draft', ...$data,
        ]);

        $post(['slug' => 'about'])->assertSessionHasErrors(['slug' => __('pages::admin.errors.slug_taken')]);
        $post(['title' => ['en' => 'About']])->assertSessionHasErrors('slug'); // the slug made from the title is checked too
        $post(['slug' => 'admin'])->assertSessionHasErrors(['slug' => __('pages::admin.errors.slug_reserved')]);
        $post(['slug' => 'ru'])->assertSessionHasErrors('slug');
        $post(['slug' => 'team'])->assertSessionHasNoErrors(); // another level
        $post(['slug' => 'admin', 'parent_id' => $about->id])->assertSessionHasNoErrors(); // /about/admin is fine
        $post(['slug' => 'x', 'layout' => 'nope'])->assertSessionHasErrors('layout');
    });

    it('moves the subpages when a page gets a new slug or parent', function () {
        $about = Page::factory()->create(['slug' => 'about']);
        $team = Page::factory()->childOf($about)->create(['slug' => 'team']);
        $lead = Page::factory()->childOf($team)->create(['slug' => 'lead']);
        $company = Page::factory()->create(['slug' => 'company']);

        $this->actingAsAdmin()->put(route('admin.pages.update', $about), [
            'title' => ['en' => 'About'], 'slug' => 'about-us', 'layout' => 'default', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        expect($lead->fresh()->path)->toBe('about-us/team/lead');

        $this->actingAsAdmin()->put(route('admin.pages.update', $team), [
            'title' => ['en' => 'Team'], 'slug' => 'team', 'parent_id' => $company->id, 'layout' => 'default', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        expect($lead->fresh()->path)->toBe('company/team/lead');

        // Not below its own subpage.
        $this->actingAsAdmin()->put(route('admin.pages.update', $company), [
            'title' => ['en' => 'Company'], 'slug' => 'company', 'parent_id' => $lead->id, 'layout' => 'default', 'status' => 'published',
        ])->assertSessionHasErrors('parent_id');
    });

    it('saves the sort order from the form, or puts a moved page last', function () {
        $about = Page::factory()->create(['slug' => 'about']);
        Page::factory()->childOf($about)->create(['slug' => 'one', 'sort_order' => 1]);
        Page::factory()->childOf($about)->create(['slug' => 'two', 'sort_order' => 2]);
        $contacts = Page::factory()->create(['slug' => 'contacts']);
        $data = ['title' => ['en' => 'Contacts'], 'slug' => 'contacts', 'layout' => 'default', 'status' => 'draft'];

        $this->actingAsAdmin()->put(route('admin.pages.update', $contacts), [...$data, 'sort_order' => 7])->assertSessionHasNoErrors();
        expect($contacts->fresh()->sort_order)->toBe(7);

        // Moved with the old number untouched: last under the new parent.
        $this->actingAsAdmin()->put(route('admin.pages.update', $contacts), [...$data, 'sort_order' => 7, 'parent_id' => $about->id])->assertSessionHasNoErrors();
        expect($contacts->fresh()->sort_order)->toBe(3);

        // Left empty, same parent: unchanged.
        $this->actingAsAdmin()->put(route('admin.pages.update', $contacts), [...$data, 'sort_order' => '', 'parent_id' => $about->id])->assertSessionHasNoErrors();
        expect($contacts->fresh()->sort_order)->toBe(3);

        $this->actingAsAdmin()->put(route('admin.pages.update', $contacts), [...$data, 'sort_order' => -1])->assertSessionHasErrors('sort_order');
    });

    it('saves a new order and the new paths, refusing clashing slugs', function () {
        $a = Page::factory()->create(['slug' => 'a']);
        $b = Page::factory()->create(['slug' => 'b']);
        $x = Page::factory()->childOf($a)->create(['slug' => 'x']);
        $bx = Page::factory()->childOf($b)->create(['slug' => 'x']);

        $this->actingAsAdmin()->postJson(route('admin.pages.reorder'), ['items' => [
            ['id' => $b->id, 'parent_id' => null],
            ['id' => $a->id, 'parent_id' => $b->id],
            ['id' => $x->id, 'parent_id' => $a->id],
            ['id' => $bx->id, 'parent_id' => $b->id],
        ]])->assertOk();

        expect($x->fresh()->path)->toBe('b/a/x')
            ->and($a->fresh()->path)->toBe('b/a')
            ->and($b->fresh()->sort_order)->toBe(1);

        // Two "x" pages under the same parent.
        $this->actingAsAdmin()->postJson(route('admin.pages.reorder'), ['items' => [
            ['id' => $b->id, 'parent_id' => null],
            ['id' => $a->id, 'parent_id' => $b->id],
            ['id' => $x->id, 'parent_id' => $b->id],
            ['id' => $bx->id, 'parent_id' => $b->id],
        ]])->assertStatus(422);
    });

    it('refuses to delete a page with subpages, and moves pages to the trash', function () {
        $about = Page::factory()->create();
        $team = Page::factory()->childOf($about)->create();

        $this->actingAsAdmin()->delete(route('admin.pages.destroy', $about))->assertSessionHas('error');
        $this->actingAsAdmin()->delete(route('admin.pages.destroy', $team))->assertSessionHas('success');

        $this->assertSoftDeleted($team);
    });
});

describe('frontend', function () {
    it('serves published pages at their path, in the layout chosen', function () {
        $about = Page::factory()->published()->create(['slug' => 'about', 'title' => ['en' => 'About', 'ru' => 'О нас'], 'layout' => 'sidebar']);
        Page::factory()->published()->childOf($about)->create(['slug' => 'team', 'title' => ['en' => 'Our team']]);

        $this->get('/about')->assertOk()->assertSee('<title>About | ', false);
        $this->get('/about/team')->assertOk()->assertSee('Our team')->assertSee('http://localhost/about', false);
        $this->get('/ru/about')->assertOk()->assertSee('О нас');
    });

    it('404s for drafts, unknown paths and pages under the wrong parent', function () {
        Page::factory()->create(['slug' => 'secret']);
        Page::factory()->published()->create(['slug' => 'team']);

        $this->get('/secret')->assertNotFound();
        $this->get('/nothing/here')->assertNotFound();
        $this->get('/about/team')->assertNotFound();
    });

    it('previews drafts for page editors', function () {
        Page::factory()->create(['slug' => 'soon', 'title' => ['en' => 'Coming soon']]);

        $this->actingAsUserWith(['pages.edit'])->get('/soon')->assertOk()->assertSee(__('pages::front.draft'));
    });

    it('never shadows the routes of the site', function () {
        $this->get('/admin')->assertRedirect(route('login')); // still the admin panel
        $this->get('/blog-or-anything-unknown')->assertNotFound();
    });

    it('offers pages to navigation links', function () {
        $page = Page::factory()->published()->create(['slug' => 'contacts', 'title' => ['en' => 'Contacts']]);

        expect(app(LinkableRegistry::class)->has(Page::class))->toBeTrue()
            ->and(Page::linkableSearch('contact')->pluck('id')->all())->toBe([$page->id])
            ->and($page->linkUrl())->toBe('http://localhost/contacts');

        app()->setLocale('ru');
        expect($page->linkUrl())->toBe('http://localhost/ru/contacts');
    });
});

it('puts Delete in the page head, Save in the Publish card, and each language in one tab', function () {
    $about = Page::factory()->create(['slug' => 'about', 'title' => ['en' => 'About']]);
    $page = Page::factory()->childOf($about)->create(['slug' => 'team']);

    $this->actingAsAdmin()->get(route('admin.pages.edit', $page))
        ->assertOk()
        ->assertSee('id="page-form"', false)
        // The page head breadcrumb uses arrow separators (odinadmin/breadcrumbs.html).
        ->assertSeeInOrder(['class="breadcrumb breadcrumb-arrow"', route('admin.pages.index'), 'aria-current="page"'], false)
        ->assertSeeInOrder(['value="DELETE"', 'id="page-form"', __('pages::admin.publish'), 'type="submit"'], false)
        // One Russian tab holds the title, the text and the search engine texts.
        ->assertSeeInOrder(['id="field-title-body-meta_title-meta_description-ru"', 'name="title[ru]"', 'name="body[ru]"',
            'name="meta_title[ru]"', 'name="meta_description[ru]"', 'id="field-title-body-meta_title-meta_description-ky"'], false)
        // The parent select shows the tree; the slug prefix is the parent's address.
        ->assertSee('data-ts="tree"', false)
        ->assertSee('tom-select.complete.min.js', false)->assertSee('tom-select.css', false)
        ->assertSee('data-depth="0" data-path="About"', false)
        ->assertSee('/about/</span>', false);

    // A page is never offered as its own parent.
    $this->actingAsAdmin()->get(route('admin.pages.edit', $about))->assertOk()->assertDontSee('data-slug="about/team"', false);

    $this->actingAsAdmin()->get(route('admin.pages.create'))->assertOk()->assertDontSee('value="DELETE"', false);
    $this->actingAsUserWith(['pages.view', 'pages.edit'])->get(route('admin.pages.edit', $page))
        ->assertOk()
        ->assertDontSee('value="DELETE"', false);
});

it('shows the pages as a tree table with drag handles, Edit and Delete', function () {
    $about = Page::factory()->published()->create(['slug' => 'about', 'title' => ['en' => 'About <us>']]);
    $team = Page::factory()->childOf($about)->create(['slug' => 'team', 'title' => ['en' => 'Team']]);

    $this->actingAsAdmin()->get(route('admin.pages.index'))->assertOk()
        ->assertSee('data-tree-table', false)
        ->assertSee('data-reorder="'.route('admin.pages.reorder').'"', false)
        ->assertSee('About &lt;us&gt;', false)
        ->assertSeeInOrder([
            'data-id="'.$about->id.'" data-parent="" data-depth="0"',
            'data-id="'.$team->id.'" data-parent="'.$about->id.'" data-depth="1"',
            '/about/team',
            __('pages::admin.statuses.draft'),
        ], false)
        ->assertSee('row-handle', false)
        ->assertSee(route('admin.pages.edit', $team), false)
        ->assertSee('action="'.route('admin.pages.destroy', $team).'"', false)
        // A page with subpages cannot be deleted: no delete form, a disabled button.
        ->assertDontSee('action="'.route('admin.pages.destroy', $about).'"', false)
        ->assertSee('title="'.__('pages::admin.errors.has_children').'"', false);

    // Without edit and delete permissions: no reordering, no buttons.
    $this->actingAsUserWith(['pages.view'])->get(route('admin.pages.index'))->assertOk()
        ->assertDontSee('data-reorder=', false)->assertDontSee('row-handle', false)
        ->assertDontSee(route('admin.pages.edit', $team), false)->assertDontSee('value="DELETE"', false);
});

it('saves a page image and removes it', function () {
    Storage::fake('public');

    $this->actingAsAdmin()->post(route('admin.pages.store'), [
        'title' => ['en' => 'About'], 'layout' => 'default', 'status' => 'published',
        'image' => UploadedFile::fake()->image('about.jpg', 1600, 900),
    ])->assertSessionHasNoErrors();

    $page = Page::sole();
    expect($page->imageUrl('image', 'thumb'))->toContain('conversions');

    $this->actingAsAdmin()->put(route('admin.pages.update', $page), [
        'title' => ['en' => 'About'], 'slug' => 'about', 'layout' => 'default', 'status' => 'published', 'image_remove' => '1',
    ])->assertSessionHasNoErrors();
    expect($page->fresh()->imageUrl('image'))->toBeNull();
});

it('answers a reorder with the new addresses', function () {
    $a = Page::factory()->create(['slug' => 'a']);
    $b = Page::factory()->create(['slug' => 'b']);

    $this->actingAsAdmin()->postJson(route('admin.pages.reorder'), ['items' => [
        ['id' => $a->id, 'parent_id' => null],
        ['id' => $b->id, 'parent_id' => $a->id],
    ]])->assertOk()->assertJsonPath("pages.{$b->id}.path", 'a/b')->assertJsonPath("pages.{$b->id}.url", 'http://localhost/a/b');
});
