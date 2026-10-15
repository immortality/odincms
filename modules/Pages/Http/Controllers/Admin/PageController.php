<?php

namespace Modules\Pages\Http\Controllers\Admin;

use App\Cms\Media\CroppedImageInput;
use App\Cms\Support\CmsCache;
use App\Cms\Support\TreeOrder;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Pages\Http\Requests\PageRequest;
use Modules\Pages\Models\Page;
use Modules\Pages\Support\ReservedSlugs;

/**
 * The page tree (jsTree: drag-and-drop, a details panel for the selected page) and the page form. Changes clear the CMS cache, since menus
 * may link to pages.
 */
class PageController extends Controller
{
    /** Levels the page tree may have. */
    public const MAX_DEPTH = 5;

    public function __construct(private CmsCache $cache) {}

    public function index(Request $request): View
    {
        Gate::authorize('pages.view');

        $search = $request->string('search')->trim()->toString();

        return view('pages::admin.index', [
            'tree' => $this->treeNodes(Page::query()->with('media')->ordered()->get()->groupBy(fn (Page $page) => $page->parent_id ?? 0)->toBase()),
            // A search shows a flat list of matches instead of the tree.
            'results' => $search !== '' ? Page::query()->search($search, ['title', 'path'])->orderBy('path')->get() : null,
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
        // Moved to another parent: put it last there.
        if ($attributes['parent_id'] !== $page->parent_id) {
            $attributes['sort_order'] = (int) Page::where('parent_id', $attributes['parent_id'])->max('sort_order') + 1;
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

        // The new addresses, for the details panel.
        return response()->json([
            'message' => __('pages::admin.order_saved'),
            'pages' => Page::query()->get()->mapWithKeys(fn (Page $page) => [$page->id => ['path' => $page->path, 'url' => $page->url()]]),
        ]);
    }

    /**
     * The pages as jsTree nodes; "data" fills the details panel of the selected page.
     *
     * @param  Collection<array-key, EloquentCollection<int, Page>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function treeNodes(Collection $byParent, int $parentId = 0): array
    {
        $layouts = Page::layouts();

        return $byParent->get($parentId, collect())->map(function (Page $page) use ($byParent, $layouts) {
            $children = $this->treeNodes($byParent, $page->id);
            $status = __('pages::admin.statuses.'.$page->status);

            return [
                'id' => (string) $page->id,
                // jsTree shows the text as HTML.
                'text' => e($page->label()).($page->isPublished() ? '' : ' <span class="badge-soft warning ms-1">'.e($status).'</span>'),
                'icon' => $children ? 'fas fa-folder' : 'far fa-file-alt',
                'state' => ['opened' => true],
                'children' => $children,
                'data' => [
                    'title' => $page->label(),
                    'slug' => $page->slug,
                    'path' => $page->path,
                    'url' => $page->url(),
                    'status' => $status,
                    'published' => $page->isPublished(),
                    'layout' => $layouts[$page->layout] ?? $page->layout,
                    'image' => $page->imageUrl('image', 'thumb'),
                    'edit' => route('admin.pages.edit', $page),
                    'destroy' => route('admin.pages.destroy', $page),
                    'delete_confirm' => __('pages::admin.delete_confirm', ['title' => $page->label()]),
                ],
            ];
        })->values()->all();
    }

    private function form(Page $page): View
    {
        return view('pages::admin.form', [
            'page' => $page,
            'parents' => Page::options($page->exists ? $page : null),
            'layouts' => Page::layouts(),
        ]);
    }
}
