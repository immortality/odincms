<?php

namespace Modules\Groups\Http\Requests;

use App\Cms\Modules\Manifest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Groups\Models\Group;

/**
 * Create or update a group. The name of a protected group cannot change.
 */
class GroupRequest extends FormRequest
{
    /**
     * Authorized by GroupPolicy before validation, so users without permission get 403, not errors.
     */
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group
            ? (bool) $this->user()?->can('update', $group)
            : (bool) $this->user()?->can('create', Group::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = $this->route('group');
        $default = config('cms.default_locale');

        return [
            'name' => [
                Rule::requiredIf(! $group instanceof Group || ! $group->isProtected()),
                'string',
                'max:64',
                'regex:'.Manifest::SLUG_PATTERN,
                Rule::unique(config('permission.table_names.roles'), 'name')
                    ->where('guard_name', 'web')
                    ->ignore($group instanceof Group ? $group->id : null),
            ],
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:255'],
            'title.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.regex' => __('groups::admin.name_format')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['name' => __('groups::admin.fields.name')];
        foreach (config('cms.locales') as $locale) {
            $attributes["title.{$locale}"] = __('groups::admin.fields.title').' ('.strtoupper($locale).')';
        }

        return $attributes;
    }

    /**
     * Attributes for the model. A protected group keeps its name.
     *
     * @return array<string, mixed>
     */
    public function modelAttributes(?Group $group = null): array
    {
        $attributes = [
            'title' => array_filter((array) $this->validated('title'), fn ($value) => filled($value)),
        ];

        if ($group === null || ! $group->isProtected()) {
            $attributes['name'] = (string) $this->validated('name');
        }

        return $attributes;
    }
}
