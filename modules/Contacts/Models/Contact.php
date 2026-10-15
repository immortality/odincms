<?php

namespace Modules\Contacts\Models;

use App\Cms\Media\Concerns\HasCroppedImages;
use App\Cms\Media\Contracts\CroppedImages;
use App\Cms\Support\CmsCache;
use App\Cms\Support\Concerns\FlushesCmsCache;
use App\Cms\Support\Concerns\HasSlug;
use App\Cms\Support\Concerns\HasStatus;
use App\Cms\Support\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Contacts\Database\Factories\ContactFactory;
use Modules\Contacts\Policies\ContactPolicy;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A contact point (an office, a branch). Title, address and city are translatable ($contact->city in the
 * current locale; getTranslations('city') for all). The coordinates are both set or both null.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $address
 * @property string|null $city
 * @property string|null $phone
 * @property string|null $email
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('contacts')]
#[Fillable(['title', 'slug', 'address', 'city', 'phone', 'email', 'latitude', 'longitude', 'status'])]
#[Translatable(['title', 'address', 'city'])]
#[UseFactory(ContactFactory::class)]
#[UsePolicy(ContactPolicy::class)]
class Contact extends Model implements CroppedImages
{
    /** @use HasFactory<ContactFactory> */
    // FlushesCmsCache: primary() is cached until a contact changes.
    use FlushesCmsCache, HasCroppedImages, HasFactory, HasSlug, HasStatus, HasTranslations, Searchable;

    /**
     * The site's contact (the first published one), shown by the theme in the top bar and footer.
     * Cached in CmsCache, so pages need no query; null when there is none.
     */
    public static function primary(): ?self
    {
        // The cache keeps the attributes (it does not unserialize objects); false stands for "none".
        $attributes = app(CmsCache::class)->rememberForever(
            'contacts.primary',
            fn () => static::published()->oldest('id')->first()?->getAttributes() ?? false,
        );

        return is_array($attributes) ? static::hydrate([$attributes])->first() : null;
    }

    /**
     * The phone as a tel: link ("+996 (312) 66-00-11" → "tel:+996312660011"), or null without a phone.
     */
    public function telUrl(): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', (string) $this->phone);

        return $digits !== '' ? 'tel:'.$digits : null;
    }

    /**
     * The address and the city in the current locale, e.g. "Manas 9, Bishkek".
     */
    public function fullAddress(): string
    {
        return implode(', ', array_filter([$this->address, $this->city], fn ($part) => filled($part)));
    }

    /**
     * The filled-in ways to reach the contact, in the order asked, for theme markup:
     * [['type' => 'email', 'text' => 'office@…', 'url' => 'mailto:office@…'], …]. The address links to
     * the map when the contact has coordinates. Icons and markup are the theme's.
     *
     * @param  list<'email'|'phone'|'address'>  $types
     * @return list<array{type: string, text: string, url: string|null}>
     */
    public function links(array $types = ['email', 'phone', 'address']): array
    {
        $links = [];

        foreach ($types as $type) {
            $link = match ($type) {
                'email' => filled($this->email) ? ['text' => (string) $this->email, 'url' => 'mailto:'.$this->email] : null,
                'phone' => filled($this->phone) ? ['text' => (string) $this->phone, 'url' => $this->telUrl()] : null,
                'address' => ($address = $this->fullAddress()) !== '' ? ['text' => $address, 'url' => $this->mapUrl()] : null,
            };

            if ($link !== null) {
                $links[] = ['type' => $type, ...$link];
            }
        }

        return $links;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * The photo is cropped in the admin to the size in module.json → "images" (Settings → Images).
     */
    public function croppedImages(): array
    {
        return ['photo' => 'contacts.photo'];
    }

    /**
     * The photo in a version: "large" (the full size) or "thumb".
     */
    public function photoUrl(string $version = 'thumb'): ?string
    {
        return $this->imageUrl('photo', $version);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * The point on openstreetmap.org, or null without coordinates.
     */
    public function mapUrl(int $zoom = 17): ?string
    {
        return $this->hasCoordinates()
            ? "https://www.openstreetmap.org/?mlat={$this->latitude}&mlon={$this->longitude}#map={$zoom}/{$this->latitude}/{$this->longitude}"
            : null;
    }
}
