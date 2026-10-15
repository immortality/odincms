<?php

namespace App\Cms\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Manual order in the `sort_order` column. A new row without a sort_order goes last among its
 * siblings (sortScope(), e.g. the same parent). reorder() saves a drag-and-drop order.
 *
 * @mixin Model
 */
trait Sortable
{
    public static function bootSortable(): void
    {
        static::creating(function (Model $model) {
            /** @var Model&self $model */
            if ($model->getAttribute('sort_order') === null) {
                $query = $model->newQuery();
                $model->sortScope($query);
                $model->setAttribute('sort_order', (int) $query->max('sort_order') + 1);
            }
        });
    }

    /**
     * Narrow "last position" to the new row's siblings (default: the whole table).
     *
     * @param  Builder<static>  $query
     */
    protected function sortScope(Builder $query): void {}

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->getQualifiedKeyName());
    }

    /**
     * Give the rows $ids the positions 1, 2, 3, … in that order.
     *
     * @param  list<int|string>  $ids
     */
    public static function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                static::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }
}
