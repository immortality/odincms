<?php

namespace Modules\Users\Support;

use App\Models\User;

/**
 * Rules that protect the site from locking itself out. They apply to everyone, including super
 * admins (who skip policies through Gate::before). Each check returns an error message, or null.
 */
class UserGuard
{
    public function superAdminRole(): string
    {
        return (string) config('cms.super_admin_role');
    }

    public function isSuperAdmin(User $user): bool
    {
        return $user->hasRole($this->superAdminRole());
    }

    /**
     * $user is the only active super admin.
     */
    public function isLastSuperAdmin(User $user): bool
    {
        if (! $this->isSuperAdmin($user) || ! $user->is_active) {
            return false;
        }

        return User::role($this->superAdminRole())->where('is_active', true)->whereKeyNot($user->getKey())->doesntExist();
    }

    public function deleteError(User $actor, User $target): ?string
    {
        return match (true) {
            $actor->is($target) => __('users::admin.errors.delete_self'),
            $this->isLastSuperAdmin($target) => __('users::admin.errors.last_super_admin'),
            $this->isSuperAdmin($target) && ! $this->isSuperAdmin($actor) => __('users::admin.errors.super_admin_only'),
            default => null,
        };
    }

    public function deactivateError(User $actor, User $target): ?string
    {
        return match (true) {
            $actor->is($target) => __('users::admin.errors.deactivate_self'),
            $this->isLastSuperAdmin($target) => __('users::admin.errors.last_super_admin'),
            $this->isSuperAdmin($target) && ! $this->isSuperAdmin($actor) => __('users::admin.errors.super_admin_only'),
            default => null,
        };
    }

    /**
     * Changing $target's groups to $groups (names). $target is null when creating a user.
     *
     * @param  list<string>  $groups
     */
    public function groupsError(User $actor, ?User $target, array $groups): ?string
    {
        $role = $this->superAdminRole();
        $grants = in_array($role, $groups, true) && ($target === null || ! $this->isSuperAdmin($target));
        $revokes = $target !== null && $this->isSuperAdmin($target) && ! in_array($role, $groups, true);

        return match (true) {
            ($grants || $revokes) && ! $this->isSuperAdmin($actor) => __('users::admin.errors.super_admin_only'),
            $revokes && $actor->is($target) => __('users::admin.errors.leave_super_admin'),
            $revokes && $this->isLastSuperAdmin($target) => __('users::admin.errors.last_super_admin'),
            default => null,
        };
    }
}
