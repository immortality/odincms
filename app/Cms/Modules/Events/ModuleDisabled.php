<?php

namespace App\Cms\Modules\Events;

use App\Cms\Modules\Module;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a module was disabled by ModuleManager.
 */
class ModuleDisabled
{
    use Dispatchable;

    public function __construct(public Module $module) {}
}
