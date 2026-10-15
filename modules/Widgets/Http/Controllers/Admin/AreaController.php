<?php

namespace Modules\Widgets\Http\Controllers\Admin;

use App\Cms\Support\CmsCache;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Widgets\Http\Requests\AreaRequest;
use Modules\Widgets\Models\WidgetArea;

/**
 * Widget areas of the current theme (config('cms.theme')).
 */
class AreaController extends Controller
{
    public function __construct(private CmsCache $cache) {}

    public function index(): View
    {
        Gate::authorize('widgets.view');

        return view('widgets::admin.areas.index', [
            'areas' => WidgetArea::where('theme', config('cms.theme'))->withCount('instances')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('widgets.create');

        return view('widgets::admin.areas.form', ['area' => new WidgetArea]);
    }

    public function store(AreaRequest $request): RedirectResponse
    {
        WidgetArea::create([...$request->modelAttributes(), 'theme' => (string) config('cms.theme')]);

        return redirect()->route('admin.widgets.areas.index')->with('success', __('widgets::admin.area_saved'));
    }

    public function edit(WidgetArea $area): View
    {
        Gate::authorize('widgets.edit');

        return view('widgets::admin.areas.form', ['area' => $area]);
    }

    public function update(AreaRequest $request, WidgetArea $area): RedirectResponse
    {
        $area->update($request->modelAttributes());
        $this->cache->flush();

        return redirect()->route('admin.widgets.areas.index')->with('success', __('widgets::admin.area_saved'));
    }

    public function destroy(WidgetArea $area): RedirectResponse
    {
        Gate::authorize('widgets.delete');

        $area->delete(); // its widgets are deleted by the foreign key
        $this->cache->flush();

        return redirect()->route('admin.widgets.areas.index')->with('success', __('widgets::admin.area_deleted'));
    }
}
