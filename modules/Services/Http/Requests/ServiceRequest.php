<?php

namespace Modules\Services\Http\Requests;

use App\Cms\Media\CroppedImageInput;
use App\Cms\Support\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Services\Models\Service;

/**
 * Create and update a service. Authorized by ServicePolicy before validation, so users
 * without permission get 403 instead of validation errors. An empty slug is made from the title.
 */
class ServiceRequest extends FormRequest
{
    /** Translatable fields => max length (null = no limit, the detail). */
    private const TRANSLATABLE = ['title' => 255, 'detail' => null, 'meta_title' => 255, 'meta_description' => 500];

    public function authorize(): bool
    {
        $item = $this->service();

        return $item !== null
            ? (bool) $this->user()?->can('update', $item)
            : (bool) $this->user()?->can('create', Service::class);
    }

    public function service(): ?Service
    {
        $item = $this->route('item');

        return $item instanceof Service ? $item : null;
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->input('slug'))) {
            $this->merge(['slug' => Slug::make((string) $this->input('slug'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $default = config('cms.default_locale');

        $rules = [
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:'.Slug::MAX_LENGTH, Rule::unique('services', 'slug')->ignore($this->service()?->id)],
            'status' => ['required', Rule::in(Service::statuses())],
            // photo, photo_crop, photo_remove (<x-admin.form.cropped-image>)
            ...CroppedImageInput::rules('photo'),
        ];

        foreach (self::TRANSLATABLE as $field => $max) {
            $rules[$field] ??= ['nullable', 'array'];
            $rules["{$field}.*"] = ['nullable', 'string', ...($max !== null ? ['max:'.$max] : [])];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'slug' => __('services::admin.fields.slug'),
            'photo' => __('services::admin.fields.photo'),
        ];

        foreach (array_keys(self::TRANSLATABLE) as $field) {
            foreach (config('cms.locales') as $locale) {
                $attributes["{$field}.{$locale}"] = __("services::admin.fields.{$field}").' ('.strtoupper($locale).')';
            }
        }

        return $attributes;
    }

    /**
     * The validated data, ready for the model: empty translations are left out.
     *
     * @return array<string, mixed>
     */
    public function modelAttributes(): array
    {
        $attributes = [];
        foreach (array_keys(self::TRANSLATABLE) as $field) {
            $attributes[$field] = array_filter((array) $this->validated($field, []), fn ($value) => filled($value));
        }

        return [
            ...$attributes,
            'slug' => $this->validated('slug') ?: null,
            'status' => (string) $this->validated('status'),
        ];
    }
}
