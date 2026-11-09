<?php

namespace Modules\Contacts\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Contacts\Models\ContactMessage;

/**
 * The messages sent from the contacts page: a list with search and a status filter, one message,
 * "handled" / "new", delete and bulk actions.
 */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ContactMessage::class);

        $perPage = min(max($request->integer('per_page', 20), 5), 100);
        $status = $request->string('status')->toString();

        $messages = ContactMessage::query()
            ->search($request->string('search')->toString(), ['name', 'email', 'phone', 'subject', 'message'])
            ->when(in_array($status, ContactMessage::statuses(), true), fn (Builder $query) => $query->where('status', $status))
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('contacts::admin.messages.index', [
            'messages' => $messages,
            'unhandled' => ContactMessage::query()->unhandled()->count(),
        ]);
    }

    public function show(ContactMessage $message): View
    {
        Gate::authorize('view', $message);

        return view('contacts::admin.messages.show', ['message' => $message]);
    }

    public function status(Request $request, ContactMessage $message): RedirectResponse
    {
        Gate::authorize('update', $message);

        $request->validate(['status' => ['required', Rule::in(ContactMessage::statuses())]]);
        $message->update(['status' => $request->string('status')->toString()]);

        return back()->with('success', __('contacts::messages.status_saved'));
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        Gate::authorize('delete', $message);

        $message->delete();

        return redirect()->route('admin.contacts.messages.index')->with('success', __('contacts::messages.deleted'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        // Permission first, so users without it get 403 rather than validation errors.
        abort_unless(Gate::any(['contacts.edit', 'contacts.delete']), 403);

        $request->validate([
            'action' => ['required', Rule::in(['handled', 'new', 'delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $action = $request->string('action')->toString();
        $messages = ContactMessage::whereKey($request->array('ids'))->get();

        foreach ($messages as $message) {
            Gate::authorize($action === 'delete' ? 'delete' : 'update', $message);
        }

        foreach ($messages as $message) {
            $action === 'delete' ? $message->delete() : $message->update(['status' => $action]);
        }

        return back()->with('success', trans_choice('contacts::messages.bulk_done', $messages->count(), ['count' => $messages->count()]));
    }
}
