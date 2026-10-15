<?php

namespace Tests\Fixtures\Content;

use App\Cms\Support\Concerns\HasSlug;
use App\Cms\Support\Concerns\HasStatus;
use App\Cms\Support\Concerns\PurifiesHtml;
use App\Cms\Support\Concerns\Searchable;
use App\Cms\Support\Concerns\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * A model using every content trait, on the "test_articles" table created by the test.
 *
 * @property int $id
 * @property string|null $group
 * @property string $title
 * @property string|null $body
 * @property string|null $note
 * @property string $slug
 * @property string $status
 * @property Carbon|null $published_at
 * @property int|null $sort_order
 */
class Article extends Model
{
    use HasSlug, HasStatus, HasTranslations, PurifiesHtml, Searchable, SoftDeletes, Sortable;

    protected $table = 'test_articles';

    protected $guarded = [];

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'sort_order' => 'integer'];
    }

    public function publishedAtColumn(): ?string
    {
        return 'published_at';
    }

    protected function purifiedAttributes(): array
    {
        return ['body', 'note'];
    }

    protected function slugScope(Builder $query): void
    {
        $query->where('group', $this->group);
    }

    protected function sortScope(Builder $query): void
    {
        $query->where('group', $this->group);
    }
}
