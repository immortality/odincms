<?php

namespace Modules\Groups\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Groups\Http\Requests\GroupRequest;
use Modules\Groups\Models\Group;
use Spatie\Permission\PermissionRegistrar;

class GroupController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Group::class);

        $groups = Group::query()
            ->where('guard_name', 'web')
            ->when($request->string('search')->trim()->toString(), function (Builder $query, string $search) {
                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('title->'.app()->getLocale(), 'like', '%'.$search.'%'));
            })
            ->withCount(['users', 'permissions'])
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('groups::admin.index', ['groups' => $groups]);
    }

    public function create(): View
    {
        Gate::authorize('create', Group::class);

        return view('groups::admin.form', ['group' => new Group]);
    }

    public function store(GroupRequest $request): RedirectResponse
    {
        Gate::authorize('create', Group::class);

        $group = Group::create([...$request->modelAttributes(), 'guard_name' => 'web']);

        return redirect()->route('admin.groups.edit', $group)->with('success', __('groups::admin.saved'));
    }

    public function edit(Group $group): View
    {
        Gate::authorize('update', $group);

        return view('groups::admin.form', ['group' => $group]);
    }

    public function update(GroupRequest $request, Group $group): RedirectResponse
    {
        Gate::authorize('update', $group);

        $group->update($request->modelAttributes($group));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.groups.edit', $group)->with('success', __('groups::admin.saved'));
    }

    public function destroy(Group $group): RedirectResponse
    {
        // Also for super admins, whom Gate::before lets past every policy.
        abort_if($group->isProtected(), 403, __('groups::admin.cannot_delete_protected'));
        Gate::authorize('delete', $group);

        $group->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.groups.index')->with('success', __('groups::admin.deleted'));
    }
}
