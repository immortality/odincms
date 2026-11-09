<?php

namespace Modules\Contacts\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use App\Cms\Settings\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Modules\Contacts\Events\ContactMessageReceived;
use Modules\Contacts\Listeners\NotifyTeam;
use Modules\Contacts\Models\Contact;

/**
 * Contacts: the admin (/admin/contacts, /admin/contacts/messages), the public contacts page (/contacts)
 * with its form, the e-mail to the team, the site contact for the theme and the page's settings.
 * Views, translations, migrations, routes and the policies (via #[UsePolicy]) are loaded by
 * ModuleServiceProvider.
 */
class ContactsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->make(Settings::class)->section('contacts', 'contacts::messages.settings.section', [
            'heading' => [
                'type' => 'translatable',
                'label' => 'contacts::messages.settings.heading',
                'help' => 'contacts::messages.settings.heading_help',
            ],
            'intro' => [
                'type' => 'translatable',
                'label' => 'contacts::messages.settings.intro',
            ],
            'notify_email' => [
                'type' => 'email',
                'label' => 'contacts::messages.settings.notify_email',
                'help' => 'contacts::messages.settings.notify_email_help',
            ],
        ], module: 'contacts', icon: 'fas fa-address-book', order: 74);
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(ContactMessageReceived::class, NotifyTeam::class);

        // Theme layouts get $siteContact (Contact::primary(), cached). While the module is disabled this
        // provider does not run, so layouts use "$siteContact ?? null".
        View::composer('themes.*.layouts.*', fn ($view) => $view->with('siteContact', Contact::primary()));
    }
}
