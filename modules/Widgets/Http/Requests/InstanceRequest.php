<?php

namespace Modules\Widgets\Http\Requests;

use App\Cms\Support\FormFields;
use App\Cms\Widgets\Widget;
use App\Cms\Widgets\WidgetRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Groups\Models\Group;
use Modules\Widgets\Models\WidgetInstance;
use Modules\Widgets\Support\Visibility;

/**
 * Create or update a widget instance: the common fields (area, title, visibility, cache) plus the
 * widget's own fields, posted as settings[...].
 */
class InstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->widgetInstance() ? 'widgets.edit' : 'widgets.create');
    }

    public function widgetInstance(): ?WidgetInstance
    {
        $instance = $this->route('instance');

        return $instance instanceof WidgetInstance ? $instance : null;
    }

    /**
     * The widget type being configured (from the instance, or the "widget" field when creating).
     */
    public function widget(): Widget
    {
        $instance = $this->widgetInstance();
        $key = $instance === null ? $this->string('widget')->toString() : $instance->widget;
        $widget = app(WidgetRegistry::class)->make($key, $instance === null ? [] : ($instance->settings ?? []));
        abort_if($widget === null, 404);

        return $widget;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'patterns' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->input('patterns_text', '')) ?: []))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'widget' => [Rule::requiredIf($this->widgetInstance() === null), 'string'],
            'area_id' => ['required', 'integer', Rule::exists('widget_areas', 'id')->where('theme', config('cms.theme'))],
            'title' => ['nullable', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'cache_ttl' => ['nullable', 'integer', 'min:0', 'max:604800'],
            'page_mode' => ['required', Rule::in(Visibility::PAGE_MODES)],
            'patterns' => [Rule::requiredIf(fn () => $this->input('page_mode') !== 'all'), 'array'],
            'patterns.*' => ['string', 'max:255'],
            'locales' => ['array'],
            'locales.*' => [Rule::in(config('cms.locales'))],
            'audience' => ['required', Rule::in(Visibility::AUDIENCES)],
            'groups' => [Rule::requiredIf(fn () => $this->input('audience') === 'groups'), 'array'],
            'groups.*' => ['string', Rule::exists(config('permission.table_names.roles'), 'name')],
        ];

        // The widget's own fields, under settings[...].
        foreach (FormFields::rules($this->widget()->normalizedFields()) as $name => $fieldRules) {
            $rules['settings.'.$name] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'patterns' => __('widgets::admin.fields.patterns'),
            'groups' => __('widgets::admin.fields.groups'),
            ...FormFields::attributes($this->widget()->normalizedFields(), 'settings.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function modelAttributes(): array
    {
        $audience = (string) $this->validated('audience');

        return [
            'area_id' => $this->integer('area_id'),
            'title' => array_filter((array) $this->validated('title', []), fn ($value) => filled($value)),
            'settings' => FormFields::values($this->widget()->normalizedFields(), (array) $this->validated('settings', [])),
            'visibility' => [
                'pages' => ['mode' => (string) $this->validated('page_mode'), 'patterns' => (array) $this->validated('patterns', [])],
                'locales' => array_values((array) $this->validated('locales', [])),
                'audience' => $audience,
                'groups' => $audience === 'groups' ? array_values((array) $this->validated('groups', [])) : [],
            ],
            'is_active' => $this->boolean('is_active'),
            'cache_ttl' => $this->filled('cache_ttl') ? $this->integer('cache_ttl') : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function groupOptions(): array
    {
        return Group::where('guard_name', 'web')->orderBy('id')->get()
            ->mapWithKeys(fn (Group $group) => [$group->name => $group->label()])->all();
    }
}
