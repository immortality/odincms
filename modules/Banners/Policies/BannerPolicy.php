<?php

namespace Modules\Banners\Policies;

use App\Models\User;
use Modules\Banners\Models\Banner;

/**
 * Maps the actions to the module's permissions (module.json → "permissions").
 * Add record-level rules here, e.g. authors may only edit their own drafts.
 */
class BannerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('banners.view');
    }

    public function create(User $user): bool
    {
        return $user->can('banners.create');
    }

    public function update(User $user, Banner $item): bool
    {
        return $user->can('banners.edit');
    }

    public function delete(User $user, Banner $item): bool
    {
        return $user->can('banners.delete');
    }
}
