<?php

namespace Modules\Services\Policies;

use App\Models\User;
use Modules\Services\Models\Service;

/**
 * Maps the actions to the module's permissions (module.json → "permissions").
 * Add record-level rules here, e.g. authors may only edit their own drafts.
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function create(User $user): bool
    {
        return $user->can('services.create');
    }

    public function update(User $user, Service $item): bool
    {
        return $user->can('services.edit');
    }

    public function delete(User $user, Service $item): bool
    {
        return $user->can('services.delete');
    }
}
