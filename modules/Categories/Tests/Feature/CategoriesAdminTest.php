<?php

use App\Cms\Support\LinkableRegistry;
use App\Models\User;
use Modules\Categories\Models\Category;
use Modules\Categories\Support\CategoryTypes;

beforeEach(function () {
    $this->useModule('categories');

    $types = app(CategoryTypes::class);
    $types->register('news', 'News', fn (Category $category) => url('/news/category/'.$category->slug));
    $types->register('products', 'Products');
});

it('sends guests to the login page and forbids users without permission', function () {
    $this->get(route('admin.categories.tree', 'news'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get(route('admin.categories.tree', 'news'))->assertForbidden();
    $this->actingAsUserWith(['categories.view'])->get(route('admin.categories.tree', 'news'))->assertOk();
});

it('opens the first type, and 404s for types no module registers', function () {
    $this->actingAsAdmin()->get(route('admin.categories.index'))->assertRedirect(route('admin.categories.tree', 'news'));
    $this->actingAsAdmin()->get(route('admin.categories.tree', 'unknown'))->assertNotFound();
});

it('explains when no module uses categories', function () {
    app()->forgetInstance(CategoryTypes::class);
    app()->singleton(CategoryTypes::class);

    $this->actingAsAdmin()->get(route('admin.categories.index'))->assertOk()->assertSee(__('categories::admin.no_types'));
});

it('shows the tree of one type', function () {
    $parent = Category::factory()->create(['type' => 'news', 'title' => ['en' => 'World']]);
    Category::factory()->childOf($parent)->create(['title' => ['en' => 'Asia']]);
    Category::factory()->create(['type' => 'products', 'title' => ['en' => 'Shoes']]);

    $this->actingAsAdmin()->get(route('admin.categories.tree', 'news'))
        ->assertOk()
        ->assertSeeInOrder(['World', 'Asia'])
        ->assertDontSee('Shoes')
        ->assertSee(route('admin.categories.tree', 'products')); // type tabs
});

it('creates a category with a slug made from the title', function () {
    $this->actingAsAdmin()->get(route('admin.categories.create', 'news'))->assertOk();

    $this->actingAsAdmin()->post(route('admin.categories.store', 'news'), [
        'title' => ['en' => 'Travel tips', 'ru' => 'Советы туристам'],
        'description' => ['en' => 'Useful', 'ru' => ''],
        'slug' => '',
        'is_active' => '1',
    ])->assertRedirect(route('admin.categories.tree', 'news'))->assertSessionHas('success');

    $category = Category::sole();
    expect($category->type)->toBe('news')
        ->and($category->slug)->toBe('travel-tips')
        ->and($category->getTranslations('title'))->toBe(['en' => 'Travel tips', 'ru' => 'Советы туристам'])
        ->and($category->getTranslations('description'))->toBe(['en' => 'Useful'])
        ->and($category->sort_order)->toBe(1);
});

it('validates the title, a unique slug per type and the parent', function () {
    Category::factory()->create(['type' => 'news', 'slug' => 'taken']);
    $product = Category::factory()->create(['type' => 'products', 'slug' => 'free']);

    $this->actingAsAdmin()->post(route('admin.categories.store', 'news'), [
        'title' => ['en' => ''],
        'slug' => 'Taken',
        'parent_id' => $product->id,
    ])->assertSessionHasErrors(['title.en', 'slug', 'parent_id']);

    // The same slug in another type is fine.
    $this->actingAsAdmin()->post(route('admin.categories.store', 'news'), ['title' => ['en' => 'Free'], 'slug' => 'free'])
        ->assertSessionHasNoErrors();
});

it('cannot move a category below itself', function () {
    $parent = Category::factory()->create(['type' => 'news']);
    $child = Category::factory()->childOf($parent)->create();

    $this->actingAsAdmin()->put(route('admin.categories.update', ['news', $parent]), [
        'title' => ['en' => 'Parent'],
        'parent_id' => $child->id,
    ])->assertSessionHasErrors('parent_id');

    $this->actingAsAdmin()->get(route('admin.categories.edit', ['news', $parent]))
        ->assertOk()
        ->assertDontSee('value="'.$child->id.'"', false); // not offered as a parent
});

it('updates a category and 404s for a category of another type', function () {
    $category = Category::factory()->create(['type' => 'news', 'title' => ['en' => 'Old']]);

    $this->actingAsAdmin()->put(route('admin.categories.update', ['news', $category]), [
        'title' => ['en' => 'New'],
        'slug' => 'new-slug',
        'is_active' => '0',
    ])->assertRedirect(route('admin.categories.tree', 'news'));

    expect($category->fresh())->title->toBe('New')->slug->toBe('new-slug')->is_active->toBeFalse();

    $this->actingAsAdmin()->get(route('admin.categories.edit', ['products', $category]))->assertNotFound();
});

it('refuses to delete a category with subcategories', function () {
    $parent = Category::factory()->create(['type' => 'news']);
    $child = Category::factory()->childOf($parent)->create();

    $this->actingAsAdmin()->delete(route('admin.categories.destroy', ['news', $parent]))->assertSessionHas('error');
    expect($parent->fresh())->not->toBeNull();

    $this->actingAsAdmin()->delete(route('admin.categories.destroy', ['news', $child]))->assertSessionHas('success');
    expect($child->fresh())->toBeNull();
});

it('saves the tree order', function () {
    [$a, $b] = Category::factory()->count(2)->create(['type' => 'news']);

    $this->actingAsAdmin()->postJson(route('admin.categories.reorder', 'news'), ['items' => [
        ['id' => $b->id, 'parent_id' => null],
        ['id' => $a->id, 'parent_id' => $b->id],
    ]])->assertOk();

    expect($a->fresh())->parent_id->toBe($b->id)->sort_order->toBe(1)
        ->and($b->fresh()->sort_order)->toBe(1);

    $this->actingAsUserWith(['categories.view'])->postJson(route('admin.categories.reorder', 'news'), ['items' => []])->assertForbidden();
});

it('offers the categories of types with a public URL to navigation links', function () {
    $news = Category::factory()->create(['type' => 'news', 'title' => ['en' => 'Sport news'], 'slug' => 'sport']);
    Category::factory()->create(['type' => 'products', 'title' => ['en' => 'Sport shoes']]);

    expect(app(LinkableRegistry::class)->has(Category::class))->toBeTrue()
        ->and(Category::linkableSearch('sport')->pluck('id')->all())->toBe([$news->id])
        ->and($news->linkUrl())->toBe(url('/news/category/sport'))
        ->and($news->linkLabel())->toBe('Sport news (News)');
});

it('lists a category with its descendants', function () {
    $root = Category::factory()->create(['type' => 'news']);
    $child = Category::factory()->childOf($root)->create();
    $grandchild = Category::factory()->childOf($child)->create();
    Category::factory()->create(['type' => 'news']);

    expect($root->descendantIds())->toEqualCanonicalizing([$root->id, $child->id, $grandchild->id])
        ->and(Category::options('news', $child))->toHaveKeys([$root->id])->not->toHaveKey($grandchild->id);
});

it('puts Save and Delete in the page head, and each language in one tab', function () {
    $category = Category::factory()->create(['type' => 'news']);

    $this->actingAsAdmin()->get(route('admin.categories.edit', ['news', $category]))
        ->assertOk()
        ->assertSee('form="category-form"', false)->assertSee('id="category-form"', false)
        ->assertSee('value="DELETE"', false)
        // One Russian tab holds the title and the description together.
        ->assertSeeInOrder(['id="field-title-description-ru"', 'name="title[ru]"', 'name="description[ru]"',
            'id="field-title-description-ky"'], false);

    $this->actingAsAdmin()->get(route('admin.categories.create', 'news'))->assertOk()->assertDontSee('value="DELETE"', false);
    $this->actingAsUserWith(['categories.view', 'categories.edit'])->get(route('admin.categories.edit', ['news', $category]))
        ->assertOk()
        ->assertDontSee('value="DELETE"', false);
});
