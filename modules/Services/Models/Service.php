<?php

namespace Modules\Services\Models;

use App\Cms\Media\Concerns\HasCroppedImages;
use App\Cms\Media\Contracts\CroppedImages;
use App\Cms\Support\Concerns\HasSlug;
use App\Cms\Support\Concerns\HasStatus;
use App\Cms\Support\Concerns\PurifiesHtml;
use App\Cms\Support\Concerns\Searchable;
use App\Cms\Support\Linkable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Services\Database\Factories\ServiceFactory;
use Modules\Services\Policies\ServicePolicy;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A service, shown at /services/{slug}. Translatable fields read in the current locale
 * ($service->title); all locales via getTranslations('title'). The detail is purified on save.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $detail
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('services')]
#[Fillable(['title', 'slug', 'detail', 'meta_title', 'meta_description', 'status'])]
#[Translatable(['title', 'detail', 'meta_title', 'meta_description'])]
#[UseFactory(ServiceFactory::class)]
#[UsePolicy(ServicePolicy::class)]
class Service extends Model implements CroppedImages, Linkable
{
    /** @use HasFactory<ServiceFactory> */
    use HasCroppedImages, HasFactory, HasSlug, HasStatus, HasTranslations, PurifiesHtml, Searchable;

    protected function purifiedAttributes(): array
    {
        return ['detail'];
    }

    /**
     * The photo is cropped in the admin to the size in module.json → "images" (Settings → Images).
     */
    public function croppedImages(): array
    {
        return ['photo' => 'services.photo'];
    }

    /**
     * The photo in a version: "large" (the full size) or "thumb".
     */
    public function photoUrl(string $version = 'thumb'): ?string
    {
        return $this->imageUrl('photo', $version);
    }

    /**
     * The beginning of the detail as plain text.
     */
    public function summary(int $length = 160): string
    {
        return Str::limit(trim(html_entity_decode(strip_tags((string) $this->detail))), $length);
    }

    public function metaTitle(): string
    {
        return (string) ($this->meta_title ?: $this->title);
    }

    public function metaDescription(): string
    {
        return (string) ($this->meta_description ?: $this->summary());
    }

    public function url(): string
    {
        return route('services.show', $this->slug);
    }

    public function linkUrl(): string
    {
        return $this->url();
    }

    public function linkLabel(): string
    {
        return $this->title.($this->isPublished() ? '' : ' ('.__('services::admin.statuses.'.$this->status).')');
    }

    public static function linkableSearch(string $query): Collection
    {
        return static::query()->search($query, ['title', 'slug'])->latest()->limit(30)->get();
    }

    public static function linkableOptions(): array
    {
        return static::query()->latest()->limit(300)->get()
            ->mapWithKeys(fn (Service $service) => [$service->id => $service->linkLabel()])
            ->all();
    }
}
