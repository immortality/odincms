<?php

namespace Modules\Permissions\Support;

use App\Cms\Modules\Module;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * The rows of the matrix: permissions grouped by module ("blog.publish" belongs to the Blog module).
 */
class PermissionMatrix
{
    /**
     * @return list<array{key: string, label: string, permissions: list<array{name: string, label: string}>}>
     */
    public function sections(): array
    {
        $moduleNames = Module::pluck('name', 'slug');

        return Permission::where('guard_name', 'web')->orderBy('name')->get()
            ->groupBy(fn (Permission $permission) => Str::before($permission->name, '.'))
            ->map(fn (Collection $permissions, string $prefix) => [
                'key' => $prefix,
                'label' => $prefix === 'admin' ? __('permissions::admin.admin_section') : (string) ($moduleNames[$prefix] ?? Str::headline($prefix)),
                'permissions' => $permissions->map(fn (Permission $permission) => [
                    'name' => $permission->name,
                    'label' => $this->label($prefix, $permission->name),
                ])->values()->all(),
            ])
            // The admin panel permission first, then the modules by name.
            ->sortBy(fn (array $section) => ($section['key'] === 'admin' ? '0' : '1').$section['label'])
            ->values()
            ->all();
    }

    /**
     * A module may name its permissions in Resources/lang/{locale}/permissions.php
     * ("publish" => "Publish posts"); otherwise "blog.publish" becomes "Publish".
     */
    private function label(string $prefix, string $name): string
    {
        $action = Str::after($name, '.');

        if ($prefix === 'admin') {
            return __('permissions::admin.admin_access');
        }

        $key = "{$prefix}::permissions.{$action}";

        return Lang::has($key) ? (string) __($key) : Str::headline($action);
    }
}
