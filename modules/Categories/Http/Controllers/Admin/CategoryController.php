<?php

namespace Modules\Categories\Http\Controllers\Admin;

use App\Cms\Support\CmsCache;
use App\Cms\Support\TreeOrder;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Categories\Http\Requests\CategoryRequest;
use Modules\Categories\Models\Category;
use Modules\Categories\Support\CategoryTypes;

/**
 * The category tree of one type (blog, products, …): drag-and-drop order and the category form.
 */
class CategoryController extends Controller
{
    /** Levels a category tree may have. */
    public const MAX_DEPTH = 5;

    public function __construct(private CategoryTypes $types, private CmsCache $cache) {}

    /**
     * The first type's tree, or a notice when no enabled module uses categories.
     */
    public function index(): View|RedirectResponse
    {
        Gate::authorize('categories.view');

        $first = array_key_first($this->types->all());

        return $first !== null
            ? redirect()->route('admin.categories.tree', $first)
            : view('categories::admin.no-types');
    }

    public function tree(string $type): View
    {
        Gate::authorize('categories.view');
        $this->ensureType($type);

        return view('categories::admin.index', [
            'type' => $type,
            'types' => $this->types->all(),
            'tree' => Category::tree($type),
        ]);
    }

    public function create(Request $request, string $type): View
    {
        Gate::authorize('categories.create');
        $this->ensureType($type);

        return $this->form($type, new Category([
            'type' => $type,
            'is_active' => true,
            'parent_id' => $request->integer('parent') ?: null,
        ]));
    }

    public function store(CategoryRequest $request, string $type): RedirectResponse
    {
        $this->ensureType($type);

        Category::create(['type' => $type, ...$request->modelAttributes()]);
        $this->cache->flush();

        return redirect()->route('admin.categories.tree', $type)->with('success', __('categories::admin.saved'));
    }

    public function edit(string $type, Category $category): View
    {
        Gate::authorize('categories.edit');
        $this->ensureType($type, $category);

        return $this->form($type, $category);
    }

    public function update(CategoryRequest $request, string $type, Category $category): RedirectResponse
    {
        $this->ensureType($type, $category);

        $attributes = $request->modelAttributes();
        // Moved to another parent: put it last there.
        if ($attributes['parent_id'] !== $category->parent_id) {
            $attributes['sort_order'] = (int) Category::ofType($type)->where('parent_id', $attributes['parent_id'])->max('sort_order') + 1;
        }

        $category->update($attributes);
        $this->cache->flush();

        return redirect()->route('admin.categories.tree', $type)->with('success', __('categories::admin.saved'));
    }

    public function destroy(string $type, Category $category): RedirectResponse
    {
        Gate::authorize('categories.delete');
        $this->ensureType($type, $category);

        if ($category->children()->exists()) {
            return back()->with('error', __('categories::admin.errors.has_children'));
        }

        $category->delete();
        $this->cache->flush();

        return redirect()->route('admin.categories.tree', $type)->with('success', __('categories::admin.deleted'));
    }

    /**
     * Save the tree order: a flat list of {id, parent_id} in display order.
     */
    public function reorder(Request $request, string $type): JsonResponse
    {
        Gate::authorize('categories.edit');
        $this->ensureType($type);

        TreeOrder::save($request, Category::class, Category::ofType($type)->pluck('id')->all(), self::MAX_DEPTH);
        $this->cache->flush();

        return response()->json(['message' => __('categories::admin.order_saved')]);
    }

    private function form(string $type, Category $category): View
    {
        return view('categories::admin.form', [
            'type' => $type,
            'typeLabel' => $this->types->label($type),
            'category' => $category,
            'parents' => Category::options($type, $category->exists ? $category : null),
            'publicUrl' => $category->exists ? $this->types->url($category) : null,
        ]);
    }

    /**
     * 404 for a type no enabled module registers, or a category of another type.
     */
    private function ensureType(string $type, ?Category $category = null): void
    {
        abort_unless($this->types->has($type), 404);
        abort_if($category !== null && $category->type !== $type, 404);
    }
}
