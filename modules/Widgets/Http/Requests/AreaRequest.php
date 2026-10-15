<?php

namespace Modules\Widgets\Http\Requests;

use App\Cms\Modules\Manifest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Widgets\Models\WidgetArea;

/**
 * Create or update a widget area of the current theme. The slug is what the theme uses.
 */
class AreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->area() ? 'widgets.edit' : 'widgets.create');
    }

    public function area(): ?WidgetArea
    {
        $area = $this->route('area');

        return $area instanceof WidgetArea ? $area : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $default = config('cms.default_locale');

        return [
            'slug' => ['required', 'string', 'max:64', 'regex:'.Manifest::SLUG_PATTERN,
                Rule::unique('widget_areas', 'slug')->where('theme', config('cms.theme'))->ignore($this->area()?->id)],
            'name' => ['required', 'array'],
            "name.{$default}" => ['required', 'string', 'max:255'],
            'name.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['slug' => __('widgets::admin.fields.slug')];
        foreach (config('cms.locales') as $locale) {
            $attributes["name.{$locale}"] = __('widgets::admin.fields.area_name').' ('.strtoupper($locale).')';
        }

        return $attributes;
    }

    /**
     * @return array{slug: string, name: array<string, string>}
     */
    public function modelAttributes(): array
    {
        return [
            'slug' => (string) $this->validated('slug'),
            'name' => array_filter((array) $this->validated('name'), fn ($value) => filled($value)),
        ];
    }
}
