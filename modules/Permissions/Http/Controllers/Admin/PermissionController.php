<?php

namespace Modules\Permissions\Http\Controllers\Admin;

use App\Cms\Support\CmsCache;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Groups\Models\Group;
use Modules\Permissions\Support\PermissionMatrix;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index(PermissionMatrix $matrix): View
    {
        Gate::authorize('permissions.view');

        $groups = Group::where('guard_name', 'web')->with('permissions')->orderBy('id')->get();

        return view('permissions::admin.index', [
            'sections' => $matrix->sections(),
            'groups' => $groups,
            'granted' => $groups->mapWithKeys(fn (Group $group) => [$group->id => $group->permissions->pluck('name')->all()])->all(),
            'canEdit' => Gate::allows('permissions.edit'),
        ]);
    }

    /**
     * Save the whole matrix. Super admins are not changed: they have every permission anyway.
     */
    public function update(Request $request, CmsCache $cache): RedirectResponse
    {
        Gate::authorize('permissions.edit');

        $request->validate([
            'matrix' => ['array'],
            'matrix.*' => ['array'],
            'matrix.*.*' => ['string'],
        ]);

        $existing = Permission::where('guard_name', 'web')->pluck('name')->all();
        $matrix = (array) $request->input('matrix', []);

        DB::transaction(function () use ($matrix, $existing) {
            foreach (Group::where('guard_name', 'web')->get() as $group) {
                if ($group->isSuperAdmin()) {
                    continue;
                }

                $group->syncPermissions(array_values(array_intersect((array) ($matrix[$group->id] ?? []), $existing)));
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        // The admin menu is cached per group set and depends on these permissions.
        $cache->flush();

        return back()->with('success', __('permissions::admin.saved'));
    }
}
