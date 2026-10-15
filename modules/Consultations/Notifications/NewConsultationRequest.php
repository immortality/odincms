<?php

namespace Modules\Consultations\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Consultations\Models\ConsultationRequest;

/**
 * The e-mail to the team about a new consultation request (in the site's default language).
 */
class NewConsultationRequest extends Notification
{
    public function __construct(public ConsultationRequest $request) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->request;
        $locale = (string) config('cms.default_locale');
        $t = fn (string $key, array $replace = []) => (string) __("consultations::admin.mail.{$key}", $replace, $locale);

        $mail = (new MailMessage)
            ->subject($t('subject', ['name' => $request->name]))
            ->replyTo($request->email, $request->name)
            ->line($t('intro'))
            ->line($t('name').': '.$request->name)
            ->line($t('email').': '.$request->email);

        if ($request->service) {
            $mail->line($t('service').': '.$request->service);
        }
        if ($request->message) {
            $mail->line($t('message').':')->line($request->message);
        }

        return $mail->action($t('open'), route('admin.consultations.show', $request));
    }
}
