<?php

namespace Modules\Contacts\Policies;

use App\Models\User;
use Modules\Contacts\Models\Contact;

/**
 * Maps the actions to the module's permissions (module.json → "permissions").
 */
class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contacts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can('contacts.edit');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->can('contacts.delete');
    }
}
