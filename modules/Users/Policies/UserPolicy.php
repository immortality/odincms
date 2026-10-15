<?php

namespace Modules\Users\Policies;

use App\Models\User;
use Modules\Users\Support\UserGuard;

/**
 * Permission checks. The lock-out rules (yourself, the last super admin) live in UserGuard,
 * because super admins skip policies.
 */
class UserPolicy
{
    public function __construct(private UserGuard $guard) {}

    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Only super admins may edit super admins.
     */
    public function update(User $user, User $target): bool
    {
        return $user->can('users.edit')
            && (! $this->guard->isSuperAdmin($target) || $this->guard->isSuperAdmin($user));
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.delete')
            && (! $this->guard->isSuperAdmin($target) || $this->guard->isSuperAdmin($user));
    }
}
