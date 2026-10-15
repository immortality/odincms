<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The logged-in user's own profile: name, email, language, avatar.
 */
class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'locale' => ['nullable', Rule::in(config('cms.locales'))],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:2048'],
            'avatar_remove' => ['boolean'],
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
            'avatar' => __('users::admin.fields.avatar'),
        ];
    }
}
