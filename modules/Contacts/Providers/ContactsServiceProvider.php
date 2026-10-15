<?php

namespace Modules\Contacts\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\View;
use Modules\Contacts\Models\Contact;

/**
 * Contacts: the admin (/admin/contacts) and the site contact for the theme. Views, translations,
 * migrations, routes and the policy (Contact → ContactPolicy, via #[UsePolicy]) are loaded by
 * ModuleServiceProvider.
 */
class ContactsServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Theme layouts get $siteContact (Contact::primary(), cached). While the module is disabled this
        // provider does not run, so layouts use "$siteContact ?? null".
        View::composer('themes.*.layouts.*', fn ($view) => $view->with('siteContact', Contact::primary()));
    }
}
