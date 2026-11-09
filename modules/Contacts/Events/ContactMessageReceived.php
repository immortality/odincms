<?php

namespace Modules\Contacts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Contacts\Models\ContactMessage;

/**
 * A visitor sent the form on the contacts page (other modules can react, e.g. a CRM sync).
 */
class ContactMessageReceived
{
    use Dispatchable;

    public function __construct(public ContactMessage $message) {}
}
