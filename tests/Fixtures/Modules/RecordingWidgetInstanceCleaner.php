<?php

namespace Tests\Fixtures\Modules;

use App\Cms\Modules\Contracts\WidgetInstanceCleaner;

class RecordingWidgetInstanceCleaner implements WidgetInstanceCleaner
{
    /** @var list<string> */
    public array $removed = [];

    public function remove(string $slug): void
    {
        $this->removed[] = $slug;
    }
}
