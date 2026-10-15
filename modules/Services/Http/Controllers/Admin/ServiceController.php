<?php

namespace Modules\Services\Http\Controllers\Admin;

use App\Cms\Media\CroppedImageInput;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Services\Http\Requests\ServiceRequest;
use Modules\Services\Models\Service;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Service::class);

        $perPage = min(max($request->integer('per_page', 20), 5), 100);

        $items = Service::query()
            ->with('media')
            ->search($request->string('search')->toString(), ['title', 'slug'])
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => match ($status) {
                Service::PUBLISHED => $query->published(),
                Service::DRAFT => $query->draft(),
                default => $query,
            })
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('services::admin.index', ['items' => $items]);
    }

    public function create(): View
    {
        Gate::authorize('create', Service::class);

        return view('services::admin.form', ['item' => new Service(['status' => Service::DRAFT])]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $item = DB::transaction(function () use ($request) {
            $item = Service::create($request->modelAttributes());
            $this->savePhoto($request, $item);

            return $item;
        });

        return redirect()->route('admin.services.edit', $item)->with('success', __('services::admin.saved'));
    }

    public function edit(Service $item): View
    {
        Gate::authorize('update', $item);

        return view('services::admin.form', ['item' => $item]);
    }

    public function update(ServiceRequest $request, Service $item): RedirectResponse
    {
        DB::transaction(function () use ($request, $item) {
            $item->update($request->modelAttributes());
            $this->savePhoto($request, $item);
        });

        return redirect()->route('admin.services.edit', $item)->with('success', __('services::admin.saved'));
    }

    public function destroy(Service $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        $item->delete(); // the photo goes with it (medialibrary)

        return redirect()->route('admin.services.index')->with('success', __('services::admin.deleted'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        // Permission first, so users without it get 403 rather than validation errors.
        abort_unless(Gate::any(['services.edit', 'services.delete']), 403);

        $request->validate([
            'action' => ['required', Rule::in(['publish', 'unpublish', 'delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $action = $request->string('action')->toString();
        $items = Service::whereKey($request->array('ids'))->get();

        foreach ($items as $item) {
            Gate::authorize($action === 'delete' ? 'delete' : 'update', $item);
        }

        foreach ($items as $item) {
            match ($action) {
                'delete' => $item->delete(),
                'publish' => $item->update(['status' => Service::PUBLISHED]),
                default => $item->update(['status' => Service::DRAFT]),
            };
        }

        return back()->with('success', trans_choice('services::admin.bulk_done', $items->count(), ['count' => $items->count()]));
    }

    private function savePhoto(Request $request, Service $item): void
    {
        CroppedImageInput::save($request, $item, 'photo');
    }
}
