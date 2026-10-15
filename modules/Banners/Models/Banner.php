<?php

namespace Modules\Banners\Models;

use App\Cms\Media\Concerns\HasCroppedImages;
use App\Cms\Media\Contracts\CroppedImages;
use App\Cms\Support\Concerns\FlushesCmsCache;
use App\Cms\Support\Concerns\HasStatus;
use App\Cms\Support\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Banners\Database\Factories\BannerFactory;
use Modules\Banners\Policies\BannerPolicy;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A banner of the hero section, shown by the "Hero banner" and "Banner slider" widgets.
 * Translatable fields read in the current locale ($banner->title); all locales via
 * getTranslations('title'). The title is followed by the highlight (the gradient words).
 *
 * @property int $id
 * @property string $title
 * @property string|null $highlight
 * @property string|null $text
 * @property string|null $badge_label
 * @property string|null $badge_text
 * @property string|null $button_label
 * @property string|null $button_url
 * @property string|null $chip_1_icon
 * @property string|null $chip_1_text
 * @property string|null $chip_2_icon
 * @property string|null $chip_2_text
 * @property int $position
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('banners')]
#[Fillable([
    'title', 'highlight', 'text', 'badge_label', 'badge_text', 'button_label', 'button_url',
    'chip_1_icon', 'chip_1_text', 'chip_2_icon', 'chip_2_text', 'position', 'status',
])]
#[Translatable(['title', 'highlight', 'text', 'badge_label', 'badge_text', 'button_label', 'chip_1_text', 'chip_2_text'])]
#[UseFactory(BannerFactory::class)]
#[UsePolicy(BannerPolicy::class)]
class Banner extends Model implements CroppedImages
{
    /** @use HasFactory<BannerFactory> */
    // FlushesCmsCache: widgets may cache their HTML (cache_ttl), so a change shows right away.
    use FlushesCmsCache, HasCroppedImages, HasFactory, HasStatus, HasTranslations, Searchable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * The image is cropped in the admin to the size in module.json → "images" (Settings → Images).
     */
    public function croppedImages(): array
    {
        return ['image' => 'banners.image'];
    }

    /**
     * The image in a version: "large" (the full size) or "thumb".
     */
    public function imageUrlFor(string $version = 'large'): ?string
    {
        return $this->imageUrl('image', $version);
    }

    /**
     * The chips shown over the image: [['icon' => 'bi-…', 'text' => …]], only those with a text.
     *
     * @return list<array{icon: string, text: string}>
     */
    public function chips(): array
    {
        $chips = [];
        foreach ([1, 2] as $n) {
            $text = (string) $this->getAttribute("chip_{$n}_text");
            if ($text !== '') {
                $icon = (string) $this->getAttribute("chip_{$n}_icon");
                $chips[] = ['icon' => 'bi-'.($icon !== '' ? preg_replace('/^bi-/', '', $icon) : 'check-circle'), 'text' => $text];
            }
        }

        return $chips;
    }

    /**
     * Ordered as in the admin: by position, then the newest first.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->latest('id');
    }

    /**
     * The title with the highlight, for lists and the widget settings.
     */
    public function label(): string
    {
        return trim($this->title.' '.$this->highlight);
    }

    /**
     * Banners for a select: id => label (drafts marked).
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return static::query()->ordered()->get()
            ->mapWithKeys(fn (Banner $banner) => [
                $banner->id => $banner->label().($banner->isPublished() ? '' : ' ('.__('banners::admin.statuses.'.$banner->status).')'),
            ])
            ->all();
    }
}
