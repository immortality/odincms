<?php

namespace Modules\Navigation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A menu (header, footer, ...), rendered with @navigation('{slug}').
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('navigation_groups')]
#[Fillable(['title', 'slug'])]
#[Translatable('title')]
class NavigationGroup extends Model
{
    use HasTranslations;

    /**
     * @return HasMany<NavigationLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(NavigationLink::class, 'group_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
