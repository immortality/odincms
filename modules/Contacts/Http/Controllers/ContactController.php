<?php

namespace Modules\Contacts\Http\Controllers;

use App\Cms\Support\Facades\Settings;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Contacts\Events\ContactMessageReceived;
use Modules\Contacts\Http\Requests\ContactMessageRequest;
use Modules\Contacts\Models\Contact;
use Modules\Contacts\Models\ContactMessage;

/**
 * The public contacts page (/contacts, /ru/contacts, …): the published contacts, a map and the form.
 */
class ContactController extends Controller
{
    public function index(): View
    {
        // Oldest first, so the site contact (Contact::primary()) leads the list.
        $contacts = Contact::published()->with('media')->oldest('id')->get();

        return view('contacts::front.index', [
            'contacts' => $contacts,
            'mapContact' => $contacts->first(fn (Contact $contact) => $contact->hasCoordinates()),
            'heading' => Settings::translated('contacts.heading', default: __('contacts::front.heading')),
            'intro' => Settings::translated('contacts.intro', default: __('contacts::front.intro')),
        ]);
    }

    public function send(ContactMessageRequest $request): RedirectResponse
    {
        $message = ContactMessage::create($request->modelAttributes());

        ContactMessageReceived::dispatch($message);

        return redirect()->to(route('contacts.index').'#contact-form')->with('contacts.sent', true);
    }
}
