<?php

namespace App\Cms\Modules\Events;

use App\Cms\Modules\Module;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a module was upgraded by ModuleManager. $module->installed_version is the new version.
 */
class ModuleUpgraded
{
    use Dispatchable;

    public function __construct(public Module $module, public string $fromVersion) {}
}
