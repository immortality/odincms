<?php

namespace Tests\Fixtures\Modules;

use App\Cms\Modules\Contracts\PermissionSynchronizer;
use App\Cms\Modules\Manifest;

class RecordingPermissionSynchronizer implements PermissionSynchronizer
{
    /** @var list<string> slugs passed to sync() */
    public array $synced = [];

    /** @var list<string> slugs passed to remove() */
    public array $removed = [];

    public function sync(Manifest $manifest): void
    {
        $this->synced[] = $manifest->slug;
    }

    public function remove(string $slug): void
    {
        $this->removed[] = $slug;
    }
}
