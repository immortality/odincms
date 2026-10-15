<?php

namespace Modules\Blog\Http\Requests;

use App\Cms\Media\CroppedImageInput;
use App\Cms\Support\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Blog\Models\Post;

/**
 * Create and update a post. Authorized by PostPolicy before validation; only users who may
 * publish (blog.publish) can choose the "published" status.
 */
class PostRequest extends FormRequest
{
    /** Translatable fields => max length (null = no limit, the body). */
    private const TRANSLATABLE = ['title' => 255, 'excerpt' => 1000, 'body' => null, 'meta_title' => 255, 'meta_description' => 500];

    public function authorize(): bool
    {
        $post = $this->blogPost();

        return $post !== null
            ? (bool) $this->user()?->can('update', $post)
            : (bool) $this->user()?->can('create', Post::class);
    }

    public function blogPost(): ?Post
    {
        $post = $this->route('post');

        return $post instanceof Post ? $post : null;
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
        $statuses = $this->user()?->can('publish', Post::class) ? Post::statuses() : [Post::DRAFT];

        $rules = [
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:'.Slug::MAX_LENGTH, Rule::notIn(Post::RESERVED_SLUGS),
                Rule::unique('blog_posts', 'slug')->ignore($this->blogPost()?->id)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('type', 'blog')],
            'status' => ['required', Rule::in($statuses)],
            'published_at' => ['nullable', 'date'],
            // cover, cover_crop, cover_remove (<x-admin.form.cropped-image>)
            ...CroppedImageInput::rules('cover'),
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
    public function messages(): array
    {
        return ['status.in' => __('blog::admin.errors.cannot_publish')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'slug' => __('blog::admin.fields.slug'),
            'category_id' => __('blog::admin.fields.category'),
            'published_at' => __('blog::admin.fields.published_at'),
            'cover' => __('blog::admin.fields.cover'),
        ];

        foreach (array_keys(self::TRANSLATABLE) as $field) {
            foreach (config('cms.locales') as $locale) {
                $attributes["{$field}.{$locale}"] = __("blog::admin.fields.{$field}").' ('.strtoupper($locale).')';
            }
        }

        return $attributes;
    }

    /**
     * The validated data for the model; empty translations are left out.
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
            'category_id' => $this->validated('category_id') ? (int) $this->validated('category_id') : null,
            'status' => (string) $this->validated('status'),
            'published_at' => $this->validated('published_at') ?: null,
        ];
    }
}
