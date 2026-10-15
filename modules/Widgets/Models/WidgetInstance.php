<?php

namespace Modules\Widgets\Models;

use App\Cms\Support\Concerns\FlushesCmsCache;
use App\Cms\Widgets\Widget;
use App\Cms\Widgets\WidgetRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A configured widget in an area.
 *
 * @property int $id
 * @property int $area_id
 * @property string $widget Widget key, e.g. "core.html"
 * @property string|null $title
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $visibility
 * @property int $sort_order
 * @property bool $is_active
 * @property int|null $cache_ttl
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('widget_instances')]
#[Fillable(['area_id', 'widget', 'title', 'settings', 'visibility', 'sort_order', 'is_active', 'cache_ttl'])]
#[Translatable('title')]
class WidgetInstance extends Model
{
    use FlushesCmsCache, HasTranslations;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'visibility' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'cache_ttl' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WidgetArea, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(WidgetArea::class, 'area_id');
    }

    /**
     * The widget with this instance's settings, or null when its type is not available
     * (its module is disabled or uninstalled).
     */
    public function makeWidget(): ?Widget
    {
        return app(WidgetRegistry::class)->make($this->widget, $this->settings ?? []);
    }
}
