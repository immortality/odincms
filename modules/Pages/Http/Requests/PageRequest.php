<?php

namespace Modules\Pages\Http\Requests;

use App\Cms\Media\CroppedImageInput;
use App\Cms\Support\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Pages\Models\Page;
use Modules\Pages\Support\ReservedSlugs;

/**
 * Create or update a page. The slug is unique among its siblings, and a top-level page cannot
 * take a URL another route owns (/blog, /admin, /ru).
 */
class PageRequest extends FormRequest
{
    /** Translatable fields => max length (null = no limit, the body). */
    private const TRANSLATABLE = ['title' => 255, 'body' => null, 'meta_title' => 255, 'meta_description' => 500];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->page() ? 'pages.edit' : 'pages.create');
    }

    public function page(): ?Page
    {
        $page = $this->route('page');

        return $page instanceof Page ? $page : null;
    }

    protected function prepareForValidation(): void
    {
        $title = (array) $this->input('title');
        $slug = filled($this->input('slug'))
            ? $this->input('slug')
            : ($title[config('cms.default_locale')] ?? collect($title)->filter()->first());

        // The slug is validated in its final form, so a title-made slug is checked too.
        $this->merge(['slug' => filled($slug) ? Slug::make((string) $slug) : null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $default = config('cms.default_locale');
        $parentId = $this->integer('parent_id') ?: null;

        $rules = [
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:'.Slug::MAX_LENGTH,
                Rule::unique('pages', 'slug')->where(fn ($query) => $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId))
                    ->ignore($this->page()?->id),
                ...($parentId === null ? [Rule::notIn(ReservedSlugs::all())] : [])],
            'parent_id' => ['nullable', 'integer', Rule::exists('pages', 'id')->whereNull('deleted_at')],
            'layout' => ['required', Rule::in(array_keys(Page::layouts()))],
            'status' => ['required', Rule::in(Page::statuses())],
            // image, image_crop, image_remove (<x-admin.form.cropped-image>)
            ...CroppedImageInput::rules('image'),
        ];

        foreach (self::TRANSLATABLE as $field => $max) {
            $rules[$field] ??= ['nullable', 'array'];
            $rules["{$field}.*"] = ['nullable', 'string', ...($max !== null ? ['max:'.$max] : [])];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $page = $this->page();
                $parentId = $this->integer('parent_id') ?: null;

                if ($page !== null && $parentId !== null && in_array($parentId, $page->descendantIds(), true)) {
                    $validator->errors()->add('parent_id', __('pages::admin.errors.parent_loop'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => __('pages::admin.errors.slug_taken'),
            'slug.not_in' => __('pages::admin.errors.slug_reserved'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'slug' => __('pages::admin.fields.slug'),
            'parent_id' => __('pages::admin.fields.parent'),
            'layout' => __('pages::admin.fields.layout'),
            'image' => __('pages::admin.fields.image'),
        ];

        foreach (array_keys(self::TRANSLATABLE) as $field) {
            foreach (config('cms.locales') as $locale) {
                $attributes["{$field}.{$locale}"] = __("pages::admin.fields.{$field}").' ('.strtoupper($locale).')';
            }
        }

        return $attributes;
    }

    /**
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
            'parent_id' => $this->validated('parent_id') ? (int) $this->validated('parent_id') : null,
            'layout' => (string) $this->validated('layout'),
            'status' => (string) $this->validated('status'),
        ];
    }
}
