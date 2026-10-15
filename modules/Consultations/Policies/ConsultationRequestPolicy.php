<?php

namespace Modules\Consultations\Policies;

use App\Models\User;
use Modules\Consultations\Models\ConsultationRequest;

/**
 * Maps the actions to the module's permissions (module.json → "permissions"). Requests are created by
 * visitors only, so there is no "create".
 */
class ConsultationRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('consultations.view');
    }

    public function view(User $user, ConsultationRequest $request): bool
    {
        return $user->can('consultations.view');
    }

    public function update(User $user, ConsultationRequest $request): bool
    {
        return $user->can('consultations.edit');
    }

    public function delete(User $user, ConsultationRequest $request): bool
    {
        return $user->can('consultations.delete');
    }
}
