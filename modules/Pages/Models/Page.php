<?php

namespace Modules\Pages\Models;

use App\Cms\Media\Concerns\HasCroppedImages;
use App\Cms\Media\Contracts\CroppedImages;
use App\Cms\Support\Concerns\HasSlug;
use App\Cms\Support\Concerns\HasStatus;
use App\Cms\Support\Concerns\PurifiesHtml;
use App\Cms\Support\Concerns\Searchable;
use App\Cms\Support\Concerns\Sortable;
use App\Cms\Support\Linkable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\Pages\Database\Factories\PageFactory;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A static page, served by the fallback route at its path: "about", "about/team" (slugs of the
 * page and its parents). Slugs are unique per parent; the path follows slug and parent changes,
 * including for the subpages.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $title
 * @property string $slug
 * @property string $path
 * @property string|null $body
 * @property string $layout
 * @property string $status
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Page|null $parent
 */
#[Table('pages')]
#[Fillable(['parent_id', 'title', 'slug', 'body', 'layout', 'status', 'meta_title', 'meta_description', 'sort_order'])]
#[Translatable(['title', 'body', 'meta_title', 'meta_description'])]
#[UseFactory(PageFactory::class)]
class Page extends Model implements CroppedImages, Linkable
{
    /** @use HasFactory<PageFactory> */
    use HasCroppedImages, HasFactory, HasSlug, HasStatus, HasTranslations, PurifiesHtml, Searchable, SoftDeletes, Sortable;

    public const DEFAULT_LAYOUT = 'default';

    protected static function booted(): void
    {
        // After HasSlug (traits boot first), so the slug is final.
        static::saving(function (Page $page) {
            $parentPath = $page->parent_id !== null ? self::withTrashed()->whereKey($page->parent_id)->value('path') : null;
            $page->path = ltrim($parentPath.'/'.$page->slug, '/');
        });

        // A new path moves the subpages too.
        static::saved(function (Page $page) {
            if ($page->wasChanged('path')) {
                $page->children()->get()->each(fn (Page $child) => $child->save());
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * The optional image is cropped in the admin to the size in module.json → "images" (Settings → Images).
     * Its URL: $page->imageUrl('image', 'thumb').
     */
    public function croppedImages(): array
    {
        return ['image' => 'pages.image'];
    }

    protected function purifiedAttributes(): array
    {
        return ['body'];
    }

    /**
     * Slugs are unique among siblings.
     *
     * @param  Builder<static>  $query
     */
    protected function slugScope(Builder $query): void
    {
        $query->where('parent_id', $this->parent_id);
    }

    /**
     * @param  Builder<static>  $query
     */
    protected function sortScope(Builder $query): void
    {
        $query->where('parent_id', $this->parent_id);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * This page's id and the ids of all its subpages.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $byParent = self::withTrashed()->get(['id', 'parent_id'])->groupBy(fn (Page $page) => $page->parent_id ?? 0);

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
     * Page options for a parent select: id => title, indented by level, without $except and its subpages.
     *
     * @return array<int, string>
     */
    public static function options(?Page $except = null): array
    {
        return collect(self::tree($except))
            ->mapWithKeys(fn (array $row) => [$row['page']->id => str_repeat('— ', $row['depth']).$row['page']->label()])
            ->all();
    }

    /**
     * Every page in tree order (each subpage right below its parent), without $except and its subpages:
     * the depth (0 = top level), the trail of titles ("About us / Team") and the number of subpages.
     *
     * @return list<array{page: Page, depth: int, trail: string, children: int}>
     */
    public static function tree(?Page $except = null): array
    {
        $byParent = self::query()->ordered()->get()->groupBy(fn (Page $page) => $page->parent_id ?? 0);
        $rows = [];

        $walk = function (int $parentId, int $depth, string $trail) use (&$walk, $byParent, $except, &$rows): void {
            foreach ($byParent->get($parentId, []) as $page) {
                if ($except !== null && $page->id === $except->id) {
                    continue;
                }

                $pageTrail = $trail === '' ? $page->label() : $trail.' / '.$page->label();
                $rows[] = ['page' => $page, 'depth' => $depth, 'trail' => $pageTrail, 'children' => $byParent->get($page->id, collect())->count()];
                $walk($page->id, $depth + 1, $pageTrail);
            }
        };
        $walk(0, 0, '');

        return $rows;
    }

    /**
     * Recompute every path from the slugs, top down (after a drag-and-drop reorder, which moves
     * pages without model events).
     */
    public static function rebuildPaths(): void
    {
        $pages = self::withTrashed()->get(['id', 'parent_id', 'slug', 'path']);
        $byParent = $pages->groupBy(fn (Page $page) => $page->parent_id ?? 0);

        $paths = [];
        $walk = function (int $parentId, string $prefix) use (&$walk, &$paths, $byParent): void {
            foreach ($byParent->get($parentId, []) as $page) {
                $paths[$page->id] = ltrim($prefix.'/'.$page->slug, '/');
                $walk($page->id, $paths[$page->id]);
            }
        };
        $walk(0, '');

        $changed = array_filter($paths, fn (string $path, int $id) => $pages->firstWhere('id', $id)?->path !== $path, ARRAY_FILTER_USE_BOTH);

        // Two steps, so that one page taking another's old path never hits the unique index.
        DB::transaction(function () use ($changed) {
            foreach (array_keys($changed) as $id) {
                self::withTrashed()->whereKey($id)->update(['path' => '#moving-'.$id]);
            }
            foreach ($changed as $id => $path) {
                self::withTrashed()->whereKey($id)->update(['path' => $path]);
            }
        });
    }

    /**
     * The theme's page layouts: resources/views/themes/{theme}/pages/{layout}.blade.php → layout => label.
     *
     * @return array<string, string>
     */
    public static function layouts(): array
    {
        $layouts = [];
        foreach (File::glob(resource_path('views/themes/'.config('cms.theme').'/pages/*.blade.php')) as $file) {
            $name = Str::before(basename($file), '.blade.php');
            $key = 'pages::admin.layouts.'.$name;
            $layouts[$name] = __($key) !== $key ? (string) __($key) : Str::headline($name);
        }

        return $layouts ?: [self::DEFAULT_LAYOUT => Str::headline(self::DEFAULT_LAYOUT)];
    }

    /**
     * The view that renders this page: its layout, or the default one if the theme has no such layout.
     */
    public function view(): string
    {
        $base = 'themes.'.config('cms.theme').'.pages.';

        return view()->exists($base.$this->layout) ? $base.$this->layout : $base.self::DEFAULT_LAYOUT;
    }

    public function label(): string
    {
        return (string) ($this->getTranslation('title', app()->getLocale(), true) ?: $this->slug);
    }

    public function metaTitle(): string
    {
        return (string) ($this->meta_title ?: $this->label());
    }

    public function metaDescription(): string
    {
        return (string) ($this->meta_description ?: $this->summary());
    }

    /**
     * The beginning of the body as plain text.
     */
    public function summary(int $length = 160): string
    {
        return Str::limit(trim(html_entity_decode(strip_tags((string) $this->body))), $length);
    }

    public function url(): string
    {
        return route('pages.show', $this->path);
    }

    public function linkUrl(): string
    {
        return $this->url();
    }

    public function linkLabel(): string
    {
        return $this->label().($this->isPublished() ? '' : ' ('.__('pages::admin.statuses.'.$this->status).')');
    }

    /**
     * All pages as a tree (subpages indented), drafts marked.
     */
    public static function linkableOptions(): array
    {
        $options = self::options();
        $drafts = self::query()->where('status', self::DRAFT)->pluck('id')->all();

        foreach ($drafts as $id) {
            if (isset($options[$id])) {
                $options[$id] .= ' ('.__('pages::admin.statuses.draft').')';
            }
        }

        return $options;
    }

    public static function linkableSearch(string $query): Collection
    {
        return static::query()->search($query, ['title', 'path'])->orderBy('path')->limit(30)->get();
    }
}
