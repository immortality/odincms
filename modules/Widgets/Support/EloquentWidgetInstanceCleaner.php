<?php

namespace Modules\Widgets\Support;

use App\Cms\Modules\Contracts\WidgetInstanceCleaner;
use App\Cms\Support\CmsCache;
use Modules\Widgets\Models\WidgetInstance;

/**
 * Uninstalling a module deletes the instances of its widgets (keys "{slug}.*").
 */
class EloquentWidgetInstanceCleaner implements WidgetInstanceCleaner
{
    public function __construct(private CmsCache $cache) {}

    public function remove(string $slug): void
    {
        WidgetInstance::where('widget', 'like', addcslashes($slug, '%_\\').'.%')->delete();
        $this->cache->flush();
    }
}
