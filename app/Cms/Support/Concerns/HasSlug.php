<?php

namespace App\Cms\Support\Concerns;

use App\Cms\Support\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

/**
 * A URL slug in the `slug` column, made unique on save.
 *
 * An empty slug is made from slugSource() (for a translatable attribute, its default-locale value);
 * a given slug is normalized with Slug::make() ("My Post" → "my-post"). A taken slug gets a suffix: "news-2".
 * Soft-deleted rows count as taken, since the unique index still holds them.
 *
 * Override slugScope() to make slugs unique per group only, e.g. per category type or per parent page.
 *
 * @mixin Model
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model) {
            /** @var Model&self $model */
            $model->ensureSlug();
        });
    }

    /**
     * The attribute the slug is made from.
     */
    protected function slugSource(): string
    {
        return 'title';
    }

    /**
     * Narrow the uniqueness check to the rows the slug must differ from (default: the whole table).
     *
     * @param  Builder<static>  $query
     */
    protected function slugScope(Builder $query): void {}

    public function ensureSlug(): void
    {
        $slug = Slug::make((string) ($this->getAttribute('slug') ?: $this->slugSourceValue()));

        $this->setAttribute('slug', $this->uniqueSlug($slug !== '' ? $slug : Str::lower(Str::random(8))));
    }

    private function slugSourceValue(): string
    {
        $source = $this->slugSource();

        if (Translations::isTranslatable($this, $source)) {
            $translations = array_filter($this->getTranslations($source), fn ($value) => filled($value));

            return (string) ($translations[config('cms.default_locale')] ?? reset($translations) ?: '');
        }

        return (string) $this->getAttribute($source);
    }

    private function uniqueSlug(string $slug): string
    {
        $candidate = $slug;

        for ($suffix = 2; $this->slugTaken($candidate); $suffix++) {
            $candidate = Str::limit($slug, Slug::MAX_LENGTH - strlen((string) $suffix) - 1, '').'-'.$suffix;
        }

        return $candidate;
    }

    private function slugTaken(string $slug): bool
    {
        $query = static::query();
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class); // trashed rows hold their slugs too
        }

        $this->slugScope($query);

        return $query->where('slug', $slug)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }
}
