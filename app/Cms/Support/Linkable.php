<?php

namespace App\Cms\Support;

use Illuminate\Support\Collection;

/**
 * A model that navigation links (and other modules) can point to: a blog post, a page, a category.
 * The link stores the model, not its URL, so it follows the model when its slug changes.
 *
 * Register the model in the module's provider: app(LinkableRegistry::class)->register(Post::class, 'blog::admin.posts');
 */
interface Linkable
{
    /**
     * The model's primary key (implemented by every Eloquent model).
     *
     * @return mixed
     */
    public function getKey();

    /**
     * The model's public URL.
     */
    public function linkUrl(): string;

    /**
     * A human-readable label (used in the link picker and when a link has no title of its own).
     */
    public function linkLabel(): string;

    /**
     * Models matching $query for the admin link picker (a few dozen at most).
     *
     * @return Collection<int, static>
     */
    public static function linkableSearch(string $query): Collection;

    /**
     * Every target for the admin link picker's select box: id => label (e.g. pages indented by level).
     *
     * @return array<int|string, string>
     */
    public static function linkableOptions(): array;
}
