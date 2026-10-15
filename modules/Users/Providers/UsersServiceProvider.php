<?php

namespace Modules\Users\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use App\Models\User;
use Modules\Users\Policies\UserPolicy;

/**
 * Manages App\Models\User (spec §8). Views (`users::`), translations and routes are loaded by
 * ModuleServiceProvider.
 */
class UsersServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [
        User::class => UserPolicy::class,
    ];
}
