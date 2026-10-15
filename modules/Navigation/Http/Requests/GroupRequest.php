<?php

namespace Modules\Navigation\Http\Requests;

use App\Cms\Modules\Manifest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Navigation\Models\NavigationGroup;

/**
 * Create or update a menu. The slug is what themes use: @navigation('{slug}').
 */
class GroupRequest extends FormRequest
{
    /** Slugs that would clash with the admin URLs. */
    private const RESERVED = ['create', 'linkables'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->group() ? 'navigation.edit' : 'navigation.create');
    }

    public function group(): ?NavigationGroup
    {
        $group = $this->route('group');

        return $group instanceof NavigationGroup ? $group : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $default = config('cms.default_locale');

        return [
            'slug' => ['required', 'string', 'max:64', 'regex:'.Manifest::SLUG_PATTERN, Rule::notIn(self::RESERVED),
                Rule::unique('navigation_groups', 'slug')->ignore($this->group()?->id)],
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:255'],
            'title.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['slug' => __('navigation::admin.fields.slug')];
        foreach (config('cms.locales') as $locale) {
            $attributes["title.{$locale}"] = __('navigation::admin.fields.title').' ('.strtoupper($locale).')';
        }

        return $attributes;
    }

    /**
     * @return array{slug: string, title: array<string, string>}
     */
    public function modelAttributes(): array
    {
        return [
            'slug' => (string) $this->validated('slug'),
            'title' => array_filter((array) $this->validated('title'), fn ($value) => filled($value)),
        ];
    }
}
