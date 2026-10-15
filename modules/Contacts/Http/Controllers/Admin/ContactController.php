<?php

namespace Modules\Contacts\Http\Controllers\Admin;

use App\Cms\Media\CroppedImageInput;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Contacts\Http\Requests\ContactRequest;
use Modules\Contacts\Models\Contact;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Contact::class);

        $perPage = min(max($request->integer('per_page', 20), 5), 100);

        $contacts = Contact::query()
            ->with('media')
            ->search($request->string('search')->toString(), ['title', 'slug', 'address', 'city', 'phone', 'email'])
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => match ($status) {
                Contact::PUBLISHED => $query->published(),
                Contact::DRAFT => $query->draft(),
                default => $query,
            })
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('contacts::admin.index', ['contacts' => $contacts]);
    }

    public function create(): View
    {
        Gate::authorize('create', Contact::class);

        return view('contacts::admin.form', ['contact' => new Contact(['status' => Contact::DRAFT])]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = DB::transaction(function () use ($request) {
            $contact = Contact::create($request->modelAttributes());
            CroppedImageInput::save($request, $contact, 'photo');

            return $contact;
        });

        return redirect()->route('admin.contacts.edit', $contact)->with('success', __('contacts::admin.saved'));
    }

    public function edit(Contact $contact): View
    {
        Gate::authorize('update', $contact);

        return view('contacts::admin.form', ['contact' => $contact]);
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        DB::transaction(function () use ($request, $contact) {
            $contact->update($request->modelAttributes());
            CroppedImageInput::save($request, $contact, 'photo');
        });

        return redirect()->route('admin.contacts.edit', $contact)->with('success', __('contacts::admin.saved'));
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $contact->delete(); // the photo goes with it (medialibrary)

        return redirect()->route('admin.contacts.index')->with('success', __('contacts::admin.deleted'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        // Permission first, so users without it get 403 rather than validation errors.
        abort_unless(Gate::any(['contacts.edit', 'contacts.delete']), 403);

        $request->validate([
            'action' => ['required', Rule::in(['publish', 'unpublish', 'delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $action = $request->string('action')->toString();
        $contacts = Contact::whereKey($request->array('ids'))->get();

        foreach ($contacts as $contact) {
            Gate::authorize($action === 'delete' ? 'delete' : 'update', $contact);
        }

        foreach ($contacts as $contact) {
            match ($action) {
                'delete' => $contact->delete(),
                'publish' => $contact->update(['status' => Contact::PUBLISHED]),
                default => $contact->update(['status' => Contact::DRAFT]),
            };
        }

        return back()->with('success', trans_choice('contacts::admin.bulk_done', $contacts->count(), ['count' => $contacts->count()]));
    }
}
