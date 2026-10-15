<?php

namespace Modules\Consultations\Listeners;

use App\Cms\Support\Facades\Settings;
use Illuminate\Support\Facades\Notification;
use Modules\Consultations\Events\ConsultationRequested;
use Modules\Consultations\Notifications\NewConsultationRequest;

/**
 * E-mails a new request to the address in Settings → Consultations (nothing when it is empty).
 */
class NotifyTeam
{
    public function handle(ConsultationRequested $event): void
    {
        $email = trim((string) Settings::get('consultations.notify_email'));

        if ($email !== '') {
            Notification::route('mail', $email)->notify(new NewConsultationRequest($event->request));
        }
    }
}
