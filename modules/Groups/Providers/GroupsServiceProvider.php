<?php

namespace Modules\Groups\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use Modules\Groups\Models\Group;
use Modules\Groups\Policies\GroupPolicy;

/**
 * Groups = spatie roles (spec §7). Views (`groups::`), translations and routes are loaded by
 * ModuleServiceProvider.
 */
class GroupsServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        Group::class => GroupPolicy::class,
    ];
}
