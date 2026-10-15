<?php

namespace Modules\Navigation\Http\Controllers\Admin;

use App\Cms\Support\CmsCache;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Navigation\Http\Requests\GroupRequest;
use Modules\Navigation\Models\NavigationGroup;

/**
 * Menus (navigation groups). Every change clears the cached menus.
 */
class GroupController extends Controller
{
    public function __construct(private CmsCache $cache) {}

    public function index(): View
    {
        Gate::authorize('navigation.view');

        return view('navigation::admin.groups.index', [
            'groups' => NavigationGroup::withCount('links')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('navigation.create');

        return view('navigation::admin.groups.form', ['group' => new NavigationGroup]);
    }

    public function store(GroupRequest $request): RedirectResponse
    {
        $group = NavigationGroup::create($request->modelAttributes());
        $this->cache->flush();

        return redirect()->route('admin.navigation.links.index', $group)->with('success', __('navigation::admin.group_saved'));
    }

    public function edit(NavigationGroup $group): View
    {
        Gate::authorize('navigation.edit');

        return view('navigation::admin.groups.form', ['group' => $group]);
    }

    public function update(GroupRequest $request, NavigationGroup $group): RedirectResponse
    {
        $group->update($request->modelAttributes());
        $this->cache->flush();

        return redirect()->route('admin.navigation.edit', $group)->with('success', __('navigation::admin.group_saved'));
    }

    public function destroy(NavigationGroup $group): RedirectResponse
    {
        Gate::authorize('navigation.delete');

        $group->delete(); // its links are deleted by the foreign key
        $this->cache->flush();

        return redirect()->route('admin.navigation.index')->with('success', __('navigation::admin.group_deleted'));
    }
}
