<?php

namespace Modules\Categories\Models;

use App\Cms\Support\Concerns\HasSlug;
use App\Cms\Support\Concerns\Searchable;
use App\Cms\Support\Concerns\Sortable;
use App\Cms\Support\Linkable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Modules\Categories\Database\Factories\CategoryFactory;
use Modules\Categories\Support\CategoryTypes;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A category of one module's content ($type, e.g. "blog"), nested through parent_id.
 * Slugs are unique per type.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $type
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $parent
 */
#[Table('categories')]
#[Fillable(['parent_id', 'type', 'title', 'slug', 'description', 'sort_order', 'is_active'])]
#[Translatable(['title', 'description'])]
#[UseFactory(CategoryFactory::class)]
class Category extends Model implements Linkable
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasSlug, HasTranslations, Searchable, Sortable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Slugs are unique per type.
     *
     * @param  Builder<static>  $query
     */
    protected function slugScope(Builder $query): void
    {
        $query->where('type', $this->type);
    }

    /**
     * @param  Builder<static>  $query
     */
    protected function sortScope(Builder $query): void
    {
        $query->where('type', $this->type)->where('parent_id', $this->parent_id);
    }

    /**
     * The categories of a type by parent id (0 = top level), in order.
     *
     * @return BaseCollection<int, Collection<int, Category>>
     */
    public static function tree(string $type, bool $activeOnly = false): BaseCollection
    {
        return self::ofType($type)
            ->when($activeOnly, fn (Builder $query) => $query->where('is_active', true))
            ->ordered()
            ->get()
            ->groupBy(fn (Category $category) => $category->parent_id ?? 0);
    }

    /**
     * Select options of a type: id => title, indented by level. Leaves out $except and its
     * descendants (a category cannot be moved below itself).
     *
     * @return array<int, string>
     */
    public static function options(string $type, ?Category $except = null, bool $activeOnly = false): array
    {
        $tree = self::tree($type, $activeOnly);
        $options = [];

        $walk = function (int $parentId, int $depth) use (&$walk, $tree, $except, &$options): void {
            foreach ($tree->get($parentId, []) as $category) {
                if ($except !== null && $category->id === $except->id) {
                    continue;
                }

                $options[$category->id] = str_repeat('— ', $depth).$category->label();
                $walk($category->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $options;
    }

    /**
     * This category's id and the ids of all categories below it.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $byParent = self::ofType($this->type)->get(['id', 'parent_id'])->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        $ids = [];
        $stack = [$this->id];
        while ($stack !== []) {
            $id = array_pop($stack);
            $ids[] = $id;
            foreach ($byParent->get($id, []) as $child) {
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * The title in the current locale (falling back to another one).
     */
    public function label(): string
    {
        return (string) ($this->getTranslation('title', app()->getLocale(), true) ?: $this->slug);
    }

    public function linkUrl(): string
    {
        return (string) app(CategoryTypes::class)->url($this);
    }

    public function linkLabel(): string
    {
        return $this->label().' ('.app(CategoryTypes::class)->label($this->type).')';
    }

    /**
     * The categories of every type with a public URL, as trees, labelled with their type.
     */
    public static function linkableOptions(): array
    {
        $types = app(CategoryTypes::class);
        $options = [];

        foreach ($types->linkable() as $type) {
            foreach (self::options($type) as $id => $label) {
                $options[$id] = $types->label($type).': '.$label;
            }
        }

        return $options;
    }

    public static function linkableSearch(string $query): BaseCollection
    {
        return static::query()
            ->whereIn('type', app(CategoryTypes::class)->linkable())
            ->search($query, ['title', 'slug'])
            ->orderBy('type')
            ->ordered()
            ->limit(30)
            ->get();
    }
}
