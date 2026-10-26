<?php

namespace Modules\Pages\Http\Controllers\Admin;

use App\Cms\Media\CroppedImageInput;
use App\Cms\Support\CmsCache;
use App\Cms\Support\TreeOrder;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Pages\Http\Requests\PageRequest;
use Modules\Pages\Models\Page;
use Modules\Pages\Support\ReservedSlugs;

/**
 * The page tree (a tree table: drag a page among its siblings, search and filter in the browser) and the
 * page form. Changes clear the CMS cache, since menus may link to pages.
 */
class PageController extends Controller
{
    /** Levels the page tree may have. */
    public const MAX_DEPTH = 5;

    public function __construct(private CmsCache $cache) {}

    public function index(): View
    {
        Gate::authorize('pages.view');

        return view('pages::admin.index', [
            'rows' => Page::tree(),
            'layouts' => Page::layouts(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('pages.create');

        return $this->form(new Page([
            'layout' => Page::DEFAULT_LAYOUT,
            'status' => Page::DRAFT,
            'parent_id' => $request->integer('parent') ?: null,
        ]));
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $page = DB::transaction(function () use ($request) {
            $page = Page::create($request->modelAttributes());
            CroppedImageInput::save($request, $page, 'image');

            return $page;
        });
        $this->cache->flush();

        return redirect()->route('admin.pages.edit', $page)->with('success', __('pages::admin.saved'));
    }

    public function edit(Page $page): View
    {
        Gate::authorize('pages.edit');

        return $this->form($page);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $attributes = $request->modelAttributes();
        $moved = $attributes['parent_id'] !== $page->parent_id;
        // Sort order left empty, or untouched while moving to another parent: last there (or unchanged).
        if ($attributes['sort_order'] === null || ($moved && $attributes['sort_order'] === $page->sort_order)) {
            if ($moved) {
                $attributes['sort_order'] = (int) Page::where('parent_id', $attributes['parent_id'])->max('sort_order') + 1;
            } else {
                unset($attributes['sort_order']);
            }
        }

        DB::transaction(function () use ($request, $page, $attributes) {
            $page->update($attributes);
            CroppedImageInput::save($request, $page, 'image');
        });
        $this->cache->flush();

        return redirect()->route('admin.pages.edit', $page)->with('success', __('pages::admin.saved'));
    }

    public function destroy(Page $page): RedirectResponse
    {
        Gate::authorize('pages.delete');

        if ($page->children()->exists()) {
            return back()->with('error', __('pages::admin.errors.has_children'));
        }

        $page->delete();
        $this->cache->flush();

        return redirect()->route('admin.pages.index')->with('success', __('pages::admin.deleted'));
    }

    /**
     * Save the tree order, then the new paths. Two pages with the same slug cannot end up side by side.
     */
    public function reorder(Request $request): JsonResponse
    {
        Gate::authorize('pages.edit');

        $items = TreeOrder::items($request);
        TreeOrder::check($items, Page::query()->pluck('id')->all(), self::MAX_DEPTH);

        $slugs = Page::withTrashed()->pluck('slug', 'id');
        $trashed = Page::onlyTrashed()->get(['parent_id', 'slug'])->map(fn (Page $page) => ($page->parent_id ?? 0).'/'.$page->slug);
        $siblings = collect($items)->map(fn (array $item) => ($item['parent_id'] ?? 0).'/'.$slugs[$item['id']]);
        abort_if($siblings->duplicates()->isNotEmpty() || $siblings->intersect($trashed)->isNotEmpty(), 422, __('pages::admin.errors.reorder_slug_taken'));

        $reserved = ReservedSlugs::all();
        foreach ($items as $item) {
            abort_if($item['parent_id'] === null && in_array($slugs[$item['id']], $reserved, true), 422,
                __('pages::admin.errors.reorder_slug_reserved', ['slug' => $slugs[$item['id']]]));
        }

        DB::transaction(function () use ($items) {
            TreeOrder::apply(Page::class, $items);
            Page::rebuildPaths();
        });
        $this->cache->flush();

        // The new addresses (an API client may move pages to other parents).
        return response()->json([
            'message' => __('pages::admin.order_saved'),
            'pages' => Page::query()->get()->mapWithKeys(fn (Page $page) => [$page->id => ['path' => $page->path, 'url' => $page->url()]]),
        ]);
    }

    private function form(Page $page): View
    {
        return view('pages::admin.form', [
            'page' => $page,
            // The parent select: the tree without this page and its subpages.
            'parents' => Page::tree($page->exists ? $page : null),
            'layouts' => Page::layouts(),
        ]);
    }
}
