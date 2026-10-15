<?php

namespace Modules\Blog\Models;

use App\Cms\Media\Concerns\HasCroppedImages;
use App\Cms\Media\Contracts\CroppedImages;
use App\Cms\Support\Concerns\HasSlug;
use App\Cms\Support\Concerns\HasStatus;
use App\Cms\Support\Concerns\PurifiesHtml;
use App\Cms\Support\Concerns\Searchable;
use App\Cms\Support\Linkable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Blog\Database\Factories\PostFactory;
use Modules\Blog\Events\PostPublished;
use Modules\Blog\Policies\PostPolicy;
use Modules\Categories\Models\Category;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A blog post. Translatable fields read in the current locale ($post->title); all locales via
 * getTranslations('title'). The body is purified on save. Publishing fires PostPublished.
 *
 * @property int $id
 * @property int|null $category_id
 * @property int|null $author_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $body
 * @property string $status
 * @property Carbon|null $published_at
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category|null $category
 * @property-read User|null $author
 */
#[Table('blog_posts')]
#[Fillable(['category_id', 'author_id', 'title', 'slug', 'excerpt', 'body', 'status', 'published_at', 'meta_title', 'meta_description'])]
#[Translatable(['title', 'excerpt', 'body', 'meta_title', 'meta_description'])]
#[UseFactory(PostFactory::class)]
#[UsePolicy(PostPolicy::class)]
class Post extends Model implements CroppedImages, Linkable
{
    /** @use HasFactory<PostFactory> */
    use HasCroppedImages, HasFactory, HasSlug, HasStatus, HasTranslations, PurifiesHtml, Searchable, SoftDeletes;

    /** Slugs that would clash with the blog's own URLs (/blog/category/…). */
    public const RESERVED_SLUGS = ['category'];

    protected static function booted(): void
    {
        static::saved(function (Post $post) {
            if ($post->status === self::PUBLISHED && $post->wasChanged('status')) {
                PostPublished::dispatch($post);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function publishedAtColumn(): ?string
    {
        return 'published_at';
    }

    protected function purifiedAttributes(): array
    {
        return ['body'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The cover is cropped in the admin to the size in module.json → "images" (Settings → Images).
     */
    public function croppedImages(): array
    {
        return ['cover' => 'blog.cover'];
    }

    /**
     * The cover in a version: "large" (the full size) or "thumb".
     */
    public function coverUrl(string $version = 'thumb'): ?string
    {
        return $this->imageUrl('cover', $version);
    }

    /**
     * The excerpt, or the beginning of the body as plain text.
     */
    public function summary(int $length = 200): string
    {
        $excerpt = (string) $this->excerpt;

        return $excerpt !== '' ? $excerpt : Str::limit(trim(html_entity_decode(strip_tags((string) $this->body))), $length);
    }

    public function metaTitle(): string
    {
        return (string) ($this->meta_title ?: $this->title);
    }

    public function metaDescription(): string
    {
        return (string) ($this->meta_description ?: $this->summary(160));
    }

    public function url(): string
    {
        return route('blog.show', $this->slug);
    }

    public function linkUrl(): string
    {
        return $this->url();
    }

    public function linkLabel(): string
    {
        return $this->title.($this->isPublished() ? '' : ' ('.__('blog::admin.statuses.'.$this->status).')');
    }

    /**
     * The newest 300 posts (for older ones, link the blog or a category).
     */
    public static function linkableOptions(): array
    {
        return static::query()->latest('published_at')->latest('id')->limit(300)->get()
            ->mapWithKeys(fn (Post $post) => [$post->id => $post->linkLabel()])
            ->all();
    }

    public static function linkableSearch(string $query): Collection
    {
        return static::query()->search($query, ['title', 'slug'])->latest('published_at')->latest('id')->limit(30)->get();
    }
}
