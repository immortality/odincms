<?php

namespace Modules\Contacts\Listeners;

use App\Cms\Support\Facades\Settings;
use Illuminate\Support\Facades\Notification;
use Modules\Contacts\Events\ContactMessageReceived;
use Modules\Contacts\Notifications\NewContactMessage;

/**
 * E-mails a new message to the address in Settings → Contacts (nothing when it is empty).
 */
class NotifyTeam
{
    public function handle(ContactMessageReceived $event): void
    {
        $email = trim((string) Settings::get('contacts.notify_email'));

        if ($email !== '') {
            Notification::route('mail', $email)->notify(new NewContactMessage($event->message));
        }
    }
}
