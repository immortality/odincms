<?php

namespace App\Cms\Modules\Support;

use App\Cms\Modules\Contracts\WidgetInstanceCleaner;

/**
 * Placeholder until widget instances exist (Phase 5, Step 5.1).
 */
class NullWidgetInstanceCleaner implements WidgetInstanceCleaner
{
    public function remove(string $slug): void
    {
        //
    }
}
