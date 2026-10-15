<?php

namespace App\Cms\Modules\Events;

use App\Cms\Modules\Module;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a module was uninstalled by ModuleManager.
 */
class ModuleUninstalled
{
    use Dispatchable;

    public function __construct(public Module $module) {}
}
