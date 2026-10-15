<?php

namespace App\Cms\Modules\Events;

use App\Cms\Modules\Module;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a module was enabled by ModuleManager.
 */
class ModuleEnabled
{
    use Dispatchable;

    public function __construct(public Module $module) {}
}
