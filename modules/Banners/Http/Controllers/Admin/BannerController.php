<?php

namespace Modules\Banners\Http\Controllers\Admin;

use App\Cms\Media\CroppedImageInput;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Banners\Http\Requests\BannerRequest;
use Modules\Banners\Models\Banner;

class BannerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Banner::class);

        $perPage = min(max($request->integer('per_page', 20), 5), 100);

        $items = Banner::query()
            ->with('media')
            ->search($request->string('search')->toString(), ['title', 'highlight', 'text'])
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => match ($status) {
                Banner::PUBLISHED => $query->published(),
                Banner::DRAFT => $query->draft(),
                default => $query,
            })
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();

        return view('banners::admin.index', ['items' => $items]);
    }

    public function create(): View
    {
        Gate::authorize('create', Banner::class);

        return view('banners::admin.form', ['item' => new Banner([
            'status' => Banner::DRAFT,
            'position' => (int) Banner::max('position') + 1,
        ])]);
    }

    public function store(BannerRequest $request): RedirectResponse
    {
        $item = DB::transaction(function () use ($request) {
            $item = Banner::create($request->modelAttributes());
            CroppedImageInput::save($request, $item, 'image');

            return $item;
        });

        return redirect()->route('admin.banners.edit', $item)->with('success', __('banners::admin.saved'));
    }

    public function edit(Banner $item): View
    {
        Gate::authorize('update', $item);

        return view('banners::admin.form', ['item' => $item]);
    }

    public function update(BannerRequest $request, Banner $item): RedirectResponse
    {
        DB::transaction(function () use ($request, $item) {
            $item->update($request->modelAttributes());
            CroppedImageInput::save($request, $item, 'image');
        });

        return redirect()->route('admin.banners.edit', $item)->with('success', __('banners::admin.saved'));
    }

    public function destroy(Banner $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        $item->delete(); // the image goes with it (medialibrary)

        return redirect()->route('admin.banners.index')->with('success', __('banners::admin.deleted'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        // Permission first, so users without it get 403 rather than validation errors.
        abort_unless(Gate::any(['banners.edit', 'banners.delete']), 403);

        $request->validate([
            'action' => ['required', Rule::in(['publish', 'unpublish', 'delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $action = $request->string('action')->toString();
        $items = Banner::whereKey($request->array('ids'))->get();

        foreach ($items as $item) {
            Gate::authorize($action === 'delete' ? 'delete' : 'update', $item);
        }

        foreach ($items as $item) {
            match ($action) {
                'delete' => $item->delete(),
                'publish' => $item->update(['status' => Banner::PUBLISHED]),
                default => $item->update(['status' => Banner::DRAFT]),
            };
        }

        return back()->with('success', trans_choice('banners::admin.bulk_done', $items->count(), ['count' => $items->count()]));
    }
}
