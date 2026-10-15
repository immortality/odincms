<?php

namespace App\Cms\Modules\Support;

use App\Cms\Modules\Contracts\PermissionSynchronizer;
use App\Cms\Modules\Manifest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Module permissions with spatie/laravel-permission (spec §7). Permissions are declared in
 * module.json and only created or removed here; the Permissions screen assigns them to groups.
 */
class SpatiePermissionSynchronizer implements PermissionSynchronizer
{
    /** The permission every admin user needs (checked by the "admin" middleware). */
    public const ADMIN_ACCESS = 'admin.access';

    public function __construct(
        private PermissionRegistrar $registrar,
        private string $guard = 'web',
    ) {}

    /**
     * Create the manifest's permissions, delete "{slug}.*" permissions it no longer declares,
     * and grant them all to the super-admin group.
     */
    public function sync(Manifest $manifest): void
    {
        $declared = $manifest->permissions();

        Permission::findOrCreate(self::ADMIN_ACCESS, $this->guard);

        foreach ($declared as $name) {
            Permission::findOrCreate($name, $this->guard);
        }

        Permission::where('guard_name', $this->guard)
            ->where('name', 'like', $this->prefixPattern($manifest->slug))
            ->whereNotIn('name', $declared)
            ->delete();

        $superAdmin = Role::findOrCreate((string) config('cms.super_admin_role'), $this->guard);
        $superAdmin->givePermissionTo([self::ADMIN_ACCESS, ...$declared]);

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Delete every "{slug}.*" permission (and its group and user assignments).
     */
    public function remove(string $slug): void
    {
        Permission::where('guard_name', $this->guard)
            ->where('name', 'like', $this->prefixPattern($slug))
            ->delete();

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * LIKE pattern for "{slug}.*", with LIKE wildcards in the slug escaped ("-" is safe, "_" never occurs).
     */
    private function prefixPattern(string $slug): string
    {
        return addcslashes($slug, '%_\\').'.%';
    }
}
