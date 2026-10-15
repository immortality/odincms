<?php

namespace Modules\Consultations\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Consultations\Models\ConsultationRequest;

/**
 * The consultation requests sent from the site: a list with search and a status filter, one request,
 * "handled" / "new", delete and bulk actions.
 */
class ConsultationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ConsultationRequest::class);

        $perPage = min(max($request->integer('per_page', 20), 5), 100);
        $status = $request->string('status')->toString();

        $consultations = ConsultationRequest::query()
            ->search($request->string('search')->toString(), ['name', 'email', 'service', 'message'])
            ->when(in_array($status, ConsultationRequest::statuses(), true), fn (Builder $query) => $query->where('status', $status))
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('consultations::admin.index', [
            'consultations' => $consultations,
            'unhandled' => ConsultationRequest::query()->unhandled()->count(),
        ]);
    }

    public function show(ConsultationRequest $consultation): View
    {
        Gate::authorize('view', $consultation);

        return view('consultations::admin.show', ['consultation' => $consultation]);
    }

    public function status(Request $request, ConsultationRequest $consultation): RedirectResponse
    {
        Gate::authorize('update', $consultation);

        $request->validate(['status' => ['required', Rule::in(ConsultationRequest::statuses())]]);
        $consultation->update(['status' => $request->string('status')->toString()]);

        return back()->with('success', __('consultations::admin.status_saved'));
    }

    public function destroy(ConsultationRequest $consultation): RedirectResponse
    {
        Gate::authorize('delete', $consultation);

        $consultation->delete();

        return redirect()->route('admin.consultations.index')->with('success', __('consultations::admin.deleted'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        // Permission first, so users without it get 403 rather than validation errors.
        abort_unless(Gate::any(['consultations.edit', 'consultations.delete']), 403);

        $request->validate([
            'action' => ['required', Rule::in(['handled', 'new', 'delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $action = $request->string('action')->toString();
        $consultations = ConsultationRequest::whereKey($request->array('ids'))->get();

        foreach ($consultations as $consultation) {
            Gate::authorize($action === 'delete' ? 'delete' : 'update', $consultation);
        }

        foreach ($consultations as $consultation) {
            $action === 'delete' ? $consultation->delete() : $consultation->update(['status' => $action]);
        }

        return back()->with('success', trans_choice('consultations::admin.bulk_done', $consultations->count(), ['count' => $consultations->count()]));
    }
}
