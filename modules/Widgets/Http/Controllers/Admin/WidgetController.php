<?php

namespace Modules\Widgets\Http\Controllers\Admin;

use App\Cms\Support\CmsCache;
use App\Cms\Widgets\Widget;
use App\Cms\Widgets\WidgetRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Widgets\Http\Requests\InstanceRequest;
use Modules\Widgets\Models\WidgetArea;
use Modules\Widgets\Models\WidgetInstance;

/**
 * The widgets board: one column per area of the current theme, the available widget types, and
 * the widget form. Every change clears the cached widget output.
 */
class WidgetController extends Controller
{
    public function __construct(
        private CmsCache $cache,
        private WidgetRegistry $registry,
    ) {}

    public function index(): View
    {
        Gate::authorize('widgets.view');

        return view('widgets::admin.index', [
            'areas' => WidgetArea::where('theme', config('cms.theme'))->with('instances')->orderBy('id')->get(),
            'types' => $this->registry->all(),
            'registry' => $this->registry,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('widgets.create');

        $key = $request->string('widget')->toString();
        $widget = $this->registry->make($key);
        abort_if($widget === null, 404);

        return $this->form(new WidgetInstance([
            'widget' => $key,
            'area_id' => $request->integer('area') ?: null,
            'is_active' => true,
            'cache_ttl' => 3600,
        ]), $widget);
    }

    public function store(InstanceRequest $request): RedirectResponse
    {
        $attributes = $request->modelAttributes();
        $attributes['widget'] = $request->string('widget')->toString();
        $attributes['sort_order'] = (int) WidgetInstance::where('area_id', $attributes['area_id'])->max('sort_order') + 1;

        WidgetInstance::create($attributes);
        $this->cache->flush();

        return redirect()->route('admin.widgets.index')->with('success', __('widgets::admin.widget_saved'));
    }

    public function edit(WidgetInstance $instance): View
    {
        Gate::authorize('widgets.edit');

        $widget = $instance->makeWidget();
        abort_if($widget === null, 404, __('widgets::admin.unavailable'));

        return $this->form($instance, $widget);
    }

    public function update(InstanceRequest $request, WidgetInstance $instance): RedirectResponse
    {
        $instance->update($request->modelAttributes());
        $this->cache->flush();

        return redirect()->route('admin.widgets.index')->with('success', __('widgets::admin.widget_saved'));
    }

    public function destroy(WidgetInstance $instance): RedirectResponse
    {
        Gate::authorize('widgets.delete');

        $instance->delete();
        $this->cache->flush();

        return redirect()->route('admin.widgets.index')->with('success', __('widgets::admin.widget_deleted'));
    }

    /**
     * Save the board after drag-and-drop: {areas: {area id: [instance ids in order]}}.
     */
    public function reorder(Request $request): JsonResponse
    {
        Gate::authorize('widgets.edit');

        $request->validate(['areas' => ['required', 'array'], 'areas.*' => ['array'], 'areas.*.*' => ['integer']]);

        $areaIds = WidgetArea::where('theme', config('cms.theme'))->pluck('id')->all();
        /** @var array<int|string, list<int>> $board */
        $board = (array) $request->input('areas');

        $sent = array_merge(...array_values(array_map('array_values', $board)));
        $known = WidgetInstance::whereIn('area_id', $areaIds)->pluck('id')->all();
        sort($sent);
        sort($known);
        abort_unless($sent === $known && array_diff(array_keys($board), $areaIds) === [], 422, __('widgets::admin.errors.reorder_mismatch'));

        DB::transaction(function () use ($board) {
            foreach ($board as $areaId => $ids) {
                foreach ($ids as $position => $id) {
                    WidgetInstance::whereKey($id)->update(['area_id' => (int) $areaId, 'sort_order' => $position + 1]);
                }
            }
        });
        $this->cache->flush();

        return response()->json(['message' => __('widgets::admin.order_saved')]);
    }

    private function form(WidgetInstance $instance, Widget $widget): View
    {
        return view('widgets::admin.form', [
            'instance' => $instance,
            'widget' => $widget,
            'fields' => $widget->normalizedFields(),
            'areas' => WidgetArea::where('theme', config('cms.theme'))->orderBy('id')->get()
                ->mapWithKeys(fn (WidgetArea $area) => [$area->id => $area->name])->all(),
            'groups' => InstanceRequest::groupOptions(),
            'visibility' => $instance->visibility ?? [],
        ]);
    }
}
