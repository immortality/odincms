<?php

namespace Modules\Widgets\Models;

use App\Cms\Support\Concerns\FlushesCmsCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A region of a theme layout, rendered with @widgetArea('{slug}').
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $theme
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('widget_areas')]
#[Fillable(['slug', 'name', 'theme'])]
#[Translatable('name')]
class WidgetArea extends Model
{
    use FlushesCmsCache, HasTranslations;

    /**
     * @return HasMany<WidgetInstance, $this>
     */
    public function instances(): HasMany
    {
        return $this->hasMany(WidgetInstance::class, 'area_id')->orderBy('sort_order')->orderBy('id');
    }
}
