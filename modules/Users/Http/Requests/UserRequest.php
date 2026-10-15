<?php

namespace Modules\Users\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Modules\Users\Support\UserGuard;

/**
 * Create or update a user in the admin. Authorized by UserPolicy before validation; the lock-out
 * rules (UserGuard) are checked as validation errors.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->target();

        return $target === null
            ? (bool) $this->user()?->can('create', User::class)
            : (bool) $this->user()?->can('update', $target);
    }

    public function target(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->target()?->id)],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'groups' => ['array'],
            'groups.*' => ['string', Rule::exists(config('permission.table_names.roles'), 'name')->where('guard_name', 'web')],
            'is_active' => ['boolean'],
            'locale' => ['nullable', Rule::in(config('cms.locales'))],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:2048'],
            'avatar_remove' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(UserGuard $guard): array
    {
        return [
            function (Validator $validator) use ($guard) {
                /** @var User $actor */
                $actor = $this->user();
                $target = $this->target();

                if ($error = $guard->groupsError($actor, $target, $this->groups())) {
                    $validator->errors()->add('groups', $error);
                }

                if ($target !== null && $target->is_active && ! $this->boolean('is_active')
                    && $error = $guard->deactivateError($actor, $target)) {
                    $validator->errors()->add('is_active', $error);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('users::admin.fields.name'),
            'email' => __('users::admin.fields.email'),
            'password' => __('users::admin.fields.password'),
            'groups' => __('users::admin.fields.groups'),
            'avatar' => __('users::admin.fields.avatar'),
        ];
    }

    /**
     * @return list<string>
     */
    public function groups(): array
    {
        return array_values(array_filter((array) $this->input('groups', []), 'is_string'));
    }
}
