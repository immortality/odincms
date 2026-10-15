<?php

namespace Modules\Navigation\Models;

use App\Cms\Support\Linkable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A link in a menu. Type "url" uses $url, "route" a named route with parameters, "model" a
 * Linkable model (its current URL is used, so the link follows slug changes).
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $parent_id
 * @property string|null $title
 * @property string $type
 * @property string|null $url
 * @property string|null $route_name
 * @property array<string, string>|null $route_parameters
 * @property string|null $linkable_type
 * @property int|null $linkable_id
 * @property string|null $css_class
 * @property bool $open_new_tab
 * @property list<string>|null $visibility
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read (Model&Linkable)|null $linkable
 */
#[Table('navigation_links')]
#[Fillable(['group_id', 'parent_id', 'title', 'type', 'url', 'route_name', 'route_parameters', 'linkable_type', 'linkable_id',
    'css_class', 'open_new_tab', 'visibility', 'sort_order', 'is_active'])]
#[Translatable('title')]
class NavigationLink extends Model
{
    use HasTranslations;

    /** model: a page, post, …; path: an address on this site ("contact"), kept in $url; route: a named route; url: any URL. */
    public const TYPES = ['model', 'path', 'route', 'url'];

    /** Visibility tokens besides group names. */
    public const GUESTS = '@guests';

    public const MEMBERS = '@members';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'route_parameters' => 'array',
            'visibility' => 'array',
            'open_new_tab' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<NavigationGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(NavigationGroup::class, 'group_id');
    }

    /**
     * @return BelongsTo<NavigationLink, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<NavigationLink, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
