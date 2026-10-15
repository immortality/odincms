<?php

namespace App\Cms\Modules\Contracts;

use App\Cms\Modules\Manifest;

/**
 * Creates and removes a module's permissions (spec §7). Implemented with spatie/laravel-permission in Phase 2.
 */
interface PermissionSynchronizer
{
    /**
     * Create the permissions declared in the manifest and grant them to the super-admin group.
     * Called on install and upgrade.
     */
    public function sync(Manifest $manifest): void;

    /**
     * Delete every permission named "{slug}.*". Called on uninstall.
     */
    public function remove(string $slug): void;
}
