<?php

namespace Modules\Navigation\Http\Controllers\Admin;

use App\Cms\Support\CmsCache;
use App\Cms\Support\LinkableRegistry;
use App\Cms\Support\TreeOrder;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Navigation\Http\Requests\LinkRequest;
use Modules\Navigation\Models\NavigationGroup;
use Modules\Navigation\Models\NavigationLink;
use Modules\Navigation\Support\NavigationRenderer;
use Modules\Navigation\Support\RouteOptions;

/**
 * The links of one menu: a drag-and-drop tree, and the link form. Every change clears the cached menus.
 */
class LinkController extends Controller
{
    /** Levels a menu may have (the default view shows two; deeper menus need their own view). */
    public const MAX_DEPTH = 3;

    public function __construct(private CmsCache $cache) {}

    public function index(NavigationGroup $group, NavigationRenderer $renderer): View
    {
        Gate::authorize('navigation.view');

        $links = $group->links()->with('linkable')->orderBy('sort_order')->orderBy('id')->get();

        return view('navigation::admin.links.index', [
            'group' => $group,
            'tree' => $links->groupBy(fn (NavigationLink $link) => $link->parent_id ?? 0),
            'renderer' => $renderer,
        ]);
    }

    public function create(Request $request, NavigationGroup $group): View
    {
        Gate::authorize('navigation.create');

        return $this->form($group, new NavigationLink([
            'type' => app(LinkableRegistry::class)->types() !== [] ? 'model' : 'path',
            'is_active' => true,
            'parent_id' => $request->integer('parent') ?: null,
        ]));
    }

    public function store(LinkRequest $request, NavigationGroup $group): RedirectResponse
    {
        $attributes = $request->modelAttributes();
        $attributes['sort_order'] = (int) $group->links()->where('parent_id', $attributes['parent_id'])->max('sort_order') + 1;

        $group->links()->create($attributes);
        $this->cache->flush();

        return redirect()->route('admin.navigation.links.index', $group)->with('success', __('navigation::admin.link_saved'));
    }

    public function edit(NavigationGroup $group, NavigationLink $link): View
    {
        Gate::authorize('navigation.edit');

        return $this->form($group, $link);
    }

    public function update(LinkRequest $request, NavigationGroup $group, NavigationLink $link): RedirectResponse
    {
        $link->update($request->modelAttributes());
        $this->cache->flush();

        return redirect()->route('admin.navigation.links.index', $group)->with('success', __('navigation::admin.link_saved'));
    }

    public function destroy(NavigationGroup $group, NavigationLink $link): RedirectResponse
    {
        Gate::authorize('navigation.delete');

        $link->delete(); // its children are deleted by the foreign key
        $this->cache->flush();

        return redirect()->route('admin.navigation.links.index', $group)->with('success', __('navigation::admin.link_deleted'));
    }

    /**
     * Save the order of the tree: a flat list of {id, parent_id} in display order.
     */
    public function reorder(Request $request, NavigationGroup $group): JsonResponse
    {
        Gate::authorize('navigation.edit');

        // Every link of the menu exactly once, parents from the same menu, no loops, not too deep.
        TreeOrder::save($request, NavigationLink::class, $group->links()->pluck('id')->all(), self::MAX_DEPTH);
        $this->cache->flush();

        return response()->json(['message' => __('navigation::admin.order_saved')]);
    }

    private function form(NavigationGroup $group, NavigationLink $link): View
    {
        $parents = $group->links()->when($link->exists, fn ($query) => $query->whereKeyNot($link->id))->orderBy('sort_order')->get()
            ->mapWithKeys(fn (NavigationLink $parent) => [$parent->id => $parent->getTranslation('title', app()->getLocale(), true) ?: '#'.$parent->id])
            ->all();

        // Kept as they are (links made in code may use parameters; the form doesn't edit them).
        $parameters = collect($link->route_parameters ?? [])->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");

        $linkableTypes = app(LinkableRegistry::class)->types();
        /** @var array<class-string, array<int|string, string>> $linkableOptions */
        $linkableOptions = [];
        foreach (array_keys($linkableTypes) as $class) {
            $linkableOptions[$class] = $class::linkableOptions();
        }

        return view('navigation::admin.links.form', [
            'group' => $group,
            'link' => $link,
            'parents' => $parents,
            'routes' => app(RouteOptions::class)->all(),
            'linkableTypes' => $linkableTypes,
            'linkableOptions' => $linkableOptions,
            // A new link starts on the first kind that has something to link to.
            'defaultLinkableType' => $link->linkable_type ?? array_key_first(array_filter($linkableOptions)) ?? array_key_first($linkableTypes),
            'visibilityOptions' => LinkRequest::visibilityOptions(),
            'routeParameters' => $parameters,
        ]);
    }
}
