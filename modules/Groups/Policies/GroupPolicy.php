<?php

namespace Modules\Groups\Policies;

use App\Models\User;
use Modules\Groups\Models\Group;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('groups.view');
    }

    public function create(User $user): bool
    {
        return $user->can('groups.create');
    }

    public function update(User $user, Group $group): bool
    {
        return $user->can('groups.edit');
    }

    /**
     * The seeded groups (super-admin, admin, user) can never be deleted. Super admins skip policies
     * (Gate::before), so GroupController enforces this for them too.
     */
    public function delete(User $user, Group $group): bool
    {
        return ! $group->isProtected() && $user->can('groups.delete');
    }
}
