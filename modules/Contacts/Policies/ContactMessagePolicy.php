<?php

namespace Modules\Contacts\Policies;

use App\Models\User;
use Modules\Contacts\Models\ContactMessage;

/**
 * Messages use the module's permissions: contacts.view to read them, contacts.edit to mark them
 * handled, contacts.delete to delete them. They are created by visitors only, so there is no "create".
 */
class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contacts.view');
    }

    public function view(User $user, ContactMessage $message): bool
    {
        return $user->can('contacts.view');
    }

    public function update(User $user, ContactMessage $message): bool
    {
        return $user->can('contacts.edit');
    }

    public function delete(User $user, ContactMessage $message): bool
    {
        return $user->can('contacts.delete');
    }
}
