<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Blog\Events\PostPublished;
use Modules\Blog\Models\Post;
use Modules\Categories\Models\Category;

beforeEach(function () {
    $this->useModule('categories');
    $this->useModule('blog');
});

/** An author: writes drafts, cannot publish. */
function blogAuthor(): User
{
    return User::factory()->create();
}

const AUTHOR_PERMISSIONS = ['blog.view', 'blog.create', 'blog.edit', 'blog.delete'];

it('sends guests to the login page and forbids users without permission', function () {
    $this->get(route('admin.blog.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.blog.index'))->assertForbidden();
});

it('lists, searches and filters posts', function () {
    $travel = Category::factory()->create(['type' => 'blog']);
    $mountains = Category::factory()->childOf($travel)->create();
    Post::factory()->published()->create(['title' => ['en' => 'Lake Issyk-Kul', 'ru' => 'Озеро Иссык-Куль'], 'category_id' => $mountains->id]);
    Post::factory()->create(['title' => ['en' => 'Draft about Osh']]);
    Post::factory()->scheduled()->create(['title' => ['en' => 'Coming soon']]);

    $this->actingAsAdmin()->get(route('admin.blog.index'))->assertOk()->assertSee(['Lake Issyk-Kul', 'Draft about Osh', 'Coming soon']);

    $this->actingAsAdmin()->get(route('admin.blog.index', ['search' => 'озеро']))->assertSee('Lake Issyk-Kul')->assertDontSee('Draft about Osh');
    $this->actingAsAdmin()->get(route('admin.blog.index', ['status' => 'draft']))->assertSee('Draft about Osh')->assertDontSee('Lake Issyk-Kul');
    $this->actingAsAdmin()->get(route('admin.blog.index', ['status' => 'scheduled']))->assertSee('Coming soon')->assertDontSee('Draft about Osh');
    // A category includes its subcategories.
    $this->actingAsAdmin()->get(route('admin.blog.index', ['category' => $travel->id]))->assertSee('Lake Issyk-Kul')->assertDontSee('Coming soon');
});

it('creates a post with translations, a cover and a purified body', function () {
    Storage::fake('public');
    $category = Category::factory()->create(['type' => 'blog']);
    $admin = User::factory()->create();

    $this->actingAsAdmin($admin)->get(route('admin.blog.create'))->assertOk();

    $this->actingAsAdmin($admin)->post(route('admin.blog.store'), [
        'title' => ['en' => 'Hello Bishkek', 'ru' => 'Привет, Бишкек', 'ky' => ''],
        'excerpt' => ['en' => 'Short'],
        'body' => ['en' => '<p onclick="x()">Body</p><script>alert(1)</script>'],
        'meta_title' => ['en' => ''],
        'slug' => '',
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => '',
        'cover' => UploadedFile::fake()->image('cover.jpg', 1600, 900),
    ])->assertRedirect()->assertSessionHas('success');

    $post = Post::sole();
    expect($post->slug)->toBe('hello-bishkek')
        ->and($post->getTranslations('title'))->toBe(['en' => 'Hello Bishkek', 'ru' => 'Привет, Бишкек'])
        ->and($post->getTranslations('meta_title'))->toBe([])
        ->and($post->getTranslation('body', 'en'))->toBe('<p>Body</p>')
        ->and($post->author_id)->toBe($admin->id)
        ->and($post->category_id)->toBe($category->id)
        ->and($post->isPublished())->toBeTrue()
        ->and($post->published_at)->not->toBeNull()
        ->and($post->coverUrl())->toContain('conversions');
});

it('validates the title, the slug and the category', function () {
    Post::factory()->create(['slug' => 'taken']);
    $productCategory = Category::factory()->create(['type' => 'products']);

    $this->actingAsAdmin()->post(route('admin.blog.store'), [
        'title' => ['en' => ''],
        'slug' => 'Taken',
        'category_id' => $productCategory->id,
        'status' => 'archived',
    ])->assertSessionHasErrors(['title.en', 'slug', 'category_id', 'status']);

    $this->actingAsAdmin()->post(route('admin.blog.store'), ['title' => ['en' => 'X'], 'slug' => 'category', 'status' => 'draft'])
        ->assertSessionHasErrors('slug');
});

it('lets authors write drafts but not publish', function () {
    $author = blogAuthor();

    $this->actingAsUserWith(AUTHOR_PERMISSIONS, $author)->post(route('admin.blog.store'), [
        'title' => ['en' => 'My draft'], 'status' => 'published',
    ])->assertSessionHasErrors(['status' => __('blog::admin.errors.cannot_publish')]);

    $this->actingAsUserWith(AUTHOR_PERMISSIONS, $author)->post(route('admin.blog.store'), [
        'title' => ['en' => 'My draft'], 'status' => 'draft',
    ])->assertSessionHasNoErrors();

    expect(Post::sole())->status->toBe('draft')->author_id->toBe($author->id);
});

it('lets authors edit only their own drafts', function () {
    $author = blogAuthor();
    $own = Post::factory()->create(['author_id' => $author->id]);
    $ownPublished = Post::factory()->published()->create(['author_id' => $author->id]);
    $others = Post::factory()->create(['author_id' => User::factory()->create()->id]);

    $as = fn () => $this->actingAsUserWith(AUTHOR_PERMISSIONS, $author);

    $as()->get(route('admin.blog.edit', $own))->assertOk();
    $as()->put(route('admin.blog.update', $own), ['title' => ['en' => 'Renamed'], 'status' => 'draft'])->assertRedirect();
    expect($own->fresh()->title)->toBe('Renamed');

    $as()->get(route('admin.blog.edit', $ownPublished))->assertForbidden();
    $as()->put(route('admin.blog.update', $others), ['title' => ['en' => 'Hacked'], 'status' => 'draft'])->assertForbidden();
    $as()->delete(route('admin.blog.destroy', $others))->assertForbidden();
    expect($others->fresh()->title)->not->toBe('Hacked');
});

it('lets editors publish any post, and fires PostPublished once', function () {
    Event::fake([PostPublished::class]);
    $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);

    $editor = fn () => $this->actingAsUserWith([...AUTHOR_PERMISSIONS, 'blog.publish']);

    $editor()->put(route('admin.blog.update', $post), ['title' => ['en' => 'Now public'], 'status' => 'published'])->assertRedirect();
    $editor()->put(route('admin.blog.update', $post), ['title' => ['en' => 'Typo fixed'], 'status' => 'published'])->assertRedirect();

    expect($post->fresh()->isPublished())->toBeTrue();
    Event::assertDispatchedTimes(PostPublished::class, 1);
});

it('crops the cover to the blog sizes (1200×630, thumb 600×315)', function () {
    Storage::fake('public');

    $this->actingAsAdmin()->post(route('admin.blog.store'), [
        'title' => ['en' => 'Cropped'], 'status' => 'draft',
        'cover' => UploadedFile::fake()->image('cover.jpg', 2000, 1500),
        'cover_crop' => json_encode(['x' => 100, 'y' => 200, 'width' => 1800, 'height' => 945]),
    ])->assertSessionHasNoErrors();

    $media = Post::sole()->getFirstMedia('cover');
    expect($media->getCustomProperty('crop'))->toBe(['x' => 100, 'y' => 200, 'width' => 1800, 'height' => 945])
        ->and(array_slice(getimagesize($media->getPath('large')), 0, 2))->toBe([1200, 630])
        ->and(array_slice(getimagesize($media->getPath('thumb')), 0, 2))->toBe([600, 315]);
});

it('puts Save and Delete in the page head, outside the form', function () {
    $post = Post::factory()->create();

    $this->actingAsAdmin()->get(route('admin.blog.edit', $post))
        ->assertOk()
        ->assertSee('form="post-form"', false)
        ->assertSee('id="post-form"', false)
        ->assertSee('value="DELETE"', false)
        ->assertSee('data-cropped-image', false)
        // One Russian tab holds the title, the summary and the text together.
        ->assertSeeInOrder(['id="field-title-excerpt-body-ru"', 'name="title[ru]"', 'name="excerpt[ru]"', 'name="body[ru]"',
            'id="field-title-excerpt-body-ky"'], false);

    // No Delete button for those who may not delete the post.
    $author = blogAuthor();
    $draft = Post::factory()->create(['author_id' => $author->id]);
    $this->actingAsUserWith(['blog.view', 'blog.edit'], $author)->get(route('admin.blog.edit', $draft))
        ->assertOk()
        ->assertSee('form="post-form"', false)
        ->assertDontSee('value="DELETE"', false); // the delete form's method field (its URL is the update URL)
});

it('removes the cover on request', function () {
    Storage::fake('public');
    $post = Post::factory()->create();
    $post->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('cover');

    $this->actingAsAdmin()->put(route('admin.blog.update', $post), [
        'title' => ['en' => 'No cover'], 'status' => 'draft', 'cover_remove' => '1',
    ])->assertRedirect();

    expect($post->fresh()->coverUrl())->toBeNull();
});

it('moves deleted posts to the trash', function () {
    $post = Post::factory()->create();

    $this->actingAsAdmin()->delete(route('admin.blog.destroy', $post))->assertRedirect(route('admin.blog.index'));

    $this->assertSoftDeleted($post);
});

it('publishes in bulk, skipping posts the user may not change', function () {
    $author = blogAuthor();
    [$a, $b] = Post::factory()->count(2)->create();

    $this->actingAsAdmin()->post(route('admin.blog.bulk'), ['action' => 'publish', 'ids' => [$a->id, $b->id]])->assertSessionHas('success');
    expect(Post::published()->count())->toBe(2);

    $this->actingAsUserWith(AUTHOR_PERMISSIONS, $author)->post(route('admin.blog.bulk'), ['action' => 'unpublish', 'ids' => [$a->id]])
        ->assertSessionHas('warning');
    expect($a->fresh()->isPublished())->toBeTrue();
});
