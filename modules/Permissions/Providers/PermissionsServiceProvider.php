<?php

namespace Modules\Permissions\Providers;

use App\Cms\Modules\ModuleServiceProvider;

/**
 * The groups × permissions matrix (spec §7). Permissions themselves are created from the module
 * manifests on install (SpatiePermissionSynchronizer); this module only assigns them to groups.
 */
class PermissionsServiceProvider extends ModuleServiceProvider
{
    //
}
