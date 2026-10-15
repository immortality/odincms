<?php

namespace App\Cms\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin list search: Post::query()->search($text, ['title', 'slug']).
 *
 * Case-insensitive "contains" over the columns; a translatable column matches in any active locale
 * (the JSON keys themselves never match).
 *
 * @mixin Model
 */
trait Searchable
{
    /**
     * @param  Builder<static>  $query
     * @param  list<string>  $columns
     */
    public function scopeSearch(Builder $query, ?string $search, array $columns): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $term = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';
        $grammar = $query->getQuery()->getGrammar();

        $query->where(function (Builder $query) use ($columns, $term, $grammar) {
            foreach ($columns as $column) {
                $wrapped = $grammar->wrap($this->qualifyColumn($column));

                if (Translations::isTranslatable($this, $column)) {
                    foreach ((array) config('cms.locales') as $locale) {
                        $query->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT({$wrapped}, ?))) LIKE ?", ['$."'.$locale.'"', $term]);
                    }
                } else {
                    $query->orWhereRaw("LOWER({$wrapped}) LIKE ?", [$term]);
                }
            }
        });
    }
}
