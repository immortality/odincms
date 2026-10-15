<?php

namespace Modules\Banners\Http\Requests;

use App\Cms\Media\CroppedImageInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Banners\Models\Banner;

/**
 * Create and update a banner. Authorized by BannerPolicy before validation, so users without
 * permission get 403 instead of validation errors.
 */
class BannerRequest extends FormRequest
{
    /** Translatable fields => max length. */
    private const TRANSLATABLE = [
        'title' => 255, 'highlight' => 255, 'text' => 1000, 'badge_label' => 32, 'badge_text' => 255,
        'button_label' => 64, 'chip_1_text' => 64, 'chip_2_text' => 64,
    ];

    public function authorize(): bool
    {
        $item = $this->banner();

        return $item !== null
            ? (bool) $this->user()?->can('update', $item)
            : (bool) $this->user()?->can('create', Banner::class);
    }

    public function banner(): ?Banner
    {
        $item = $this->route('item');

        return $item instanceof Banner ? $item : null;
    }

    protected function prepareForValidation(): void
    {
        // Icon names are accepted with or without the "bi-" prefix ("bi-shield-check" → "shield-check").
        foreach (['chip_1_icon', 'chip_2_icon'] as $field) {
            if (filled($this->input($field))) {
                $this->merge([$field => preg_replace('/^bi-/', '', trim((string) $this->input($field)))]);
            }
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
            // A full address, a path on the site or an anchor; no "javascript:" links.
            'button_url' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/|#|mailto:|tel:)/i'],
            'chip_1_icon' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'chip_2_icon' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['required', Rule::in(Banner::statuses())],
            // image, image_crop, image_remove (<x-admin.form.cropped-image>)
            ...CroppedImageInput::rules('image'),
        ];

        foreach (self::TRANSLATABLE as $field => $max) {
            $rules[$field] ??= ['nullable', 'array'];
            $rules["{$field}.*"] = ['nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];
        foreach (['button_url', 'chip_1_icon', 'chip_2_icon', 'position', 'status', 'image'] as $field) {
            $attributes[$field] = __("banners::admin.fields.{$field}");
        }

        foreach (array_keys(self::TRANSLATABLE) as $field) {
            foreach (config('cms.locales') as $locale) {
                $attributes["{$field}.{$locale}"] = __("banners::admin.fields.{$field}").' ('.strtoupper($locale).')';
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'button_url.regex' => __('banners::admin.button_url_invalid'),
            'chip_1_icon.regex' => __('banners::admin.icon_invalid'),
            'chip_2_icon.regex' => __('banners::admin.icon_invalid'),
        ];
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
            'button_url' => $this->validated('button_url') ?: null,
            'chip_1_icon' => $this->validated('chip_1_icon') ?: null,
            'chip_2_icon' => $this->validated('chip_2_icon') ?: null,
            'position' => (int) $this->validated('position', 0),
            'status' => (string) $this->validated('status'),
        ];
    }
}
