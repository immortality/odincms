<?php

namespace Modules\Contacts\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Contacts\Models\ContactMessage;

/**
 * The e-mail to the team about a new message from the contacts page (in the site's default language).
 */
class NewContactMessage extends Notification
{
    public function __construct(public ContactMessage $message) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->message;
        $locale = (string) config('cms.default_locale');
        $t = fn (string $key, array $replace = []) => (string) __("contacts::messages.mail.{$key}", $replace, $locale);

        $mail = (new MailMessage)
            ->subject($t('subject', ['name' => $message->name]))
            ->replyTo($message->email, $message->name)
            ->line($t('intro'))
            ->line($t('name').': '.$message->name)
            ->line($t('email').': '.$message->email);

        if ($message->phone) {
            $mail->line($t('phone').': '.$message->phone);
        }
        if ($message->subject) {
            $mail->line($t('subject_field').': '.$message->subject);
        }

        return $mail->line($t('message').':')
            ->line($message->message)
            ->action($t('open'), route('admin.contacts.messages.show', $message));
    }
}
