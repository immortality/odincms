<?php

namespace App\Cms\Support\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Draft / published content in the `status` column.
 *
 * A model with a publish date returns its column from publishedAtColumn() (e.g. "published_at"):
 * publishing sets it to now when empty, and published() then leaves out content scheduled for later.
 *
 * @mixin Model
 */
trait HasStatus
{
    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public static function bootHasStatus(): void
    {
        static::saving(function (Model $model) {
            /** @var Model&self $model */
            $column = $model->publishedAtColumn();

            if ($column !== null && $model->getAttribute('status') === self::PUBLISHED && $model->getAttribute($column) === null) {
                $model->setAttribute($column, now());
            }
        });
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [self::DRAFT, self::PUBLISHED];
    }

    /**
     * The publish date column, or null when the model has none.
     */
    public function publishedAtColumn(): ?string
    {
        return null;
    }

    /**
     * Published, and (with a publish date) not scheduled for later.
     *
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), self::PUBLISHED);

        if (($column = $this->publishedAtColumn()) !== null) {
            $query->where(fn (Builder $query) => $query
                ->whereNull($this->qualifyColumn($column))
                ->orWhere($this->qualifyColumn($column), '<=', now()));
        }
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeDraft(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), self::DRAFT);
    }

    public function isPublished(): bool
    {
        if ($this->getAttribute('status') !== self::PUBLISHED) {
            return false;
        }

        $date = ($column = $this->publishedAtColumn()) !== null ? $this->getAttribute($column) : null;

        return ! $date instanceof DateTimeInterface || $date <= now();
    }

    /**
     * Published with a publish date in the future.
     */
    public function isScheduled(): bool
    {
        return $this->getAttribute('status') === self::PUBLISHED && ! $this->isPublished();
    }
}
