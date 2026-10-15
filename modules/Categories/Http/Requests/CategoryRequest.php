<?php

namespace Modules\Categories\Http\Requests;

use App\Cms\Support\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Categories\Models\Category;

/**
 * Create or update a category of the type in the URL. An empty slug is made from the title.
 */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->category() ? 'categories.edit' : 'categories.create');
    }

    public function category(): ?Category
    {
        $category = $this->route('category');

        return $category instanceof Category ? $category : null;
    }

    public function type(): string
    {
        return (string) $this->route('type');
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

        return [
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:255'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:2000'],
            'slug' => ['nullable', 'string', 'max:'.Slug::MAX_LENGTH,
                Rule::unique('categories', 'slug')->where('type', $this->type())->ignore($this->category()?->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('type', $this->type())],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $category = $this->category();
                $parentId = $this->integer('parent_id') ?: null;

                if ($category !== null && $parentId !== null && in_array($parentId, $category->descendantIds(), true)) {
                    $validator->errors()->add('parent_id', __('categories::admin.errors.parent_loop'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'slug' => __('categories::admin.fields.slug'),
            'parent_id' => __('categories::admin.fields.parent'),
        ];
        foreach (config('cms.locales') as $locale) {
            $attributes["title.{$locale}"] = __('categories::admin.fields.title').' ('.strtoupper($locale).')';
            $attributes["description.{$locale}"] = __('categories::admin.fields.description').' ('.strtoupper($locale).')';
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function modelAttributes(): array
    {
        $filled = fn ($value) => filled($value);

        return [
            'title' => array_filter((array) $this->validated('title'), $filled),
            'description' => array_filter((array) $this->validated('description', []), $filled),
            'slug' => $this->validated('slug') ?: null,
            'parent_id' => $this->validated('parent_id') ? (int) $this->validated('parent_id') : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
