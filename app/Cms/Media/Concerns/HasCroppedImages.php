<?php

namespace App\Cms\Media\Concerns;

use App\Cms\Media\CroppedImageInput;
use App\Cms\Media\ImageSizes;
use App\Cms\Media\ImageSpec;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Images that admins crop when uploading (<x-admin.form.cropped-image>), in the sizes the module
 * declares (module.json → "images", overridable in Admin → Settings → Images).
 *
 * The original is kept with the crop area (custom property "crop"), and each version ("large",
 * "thumb", …) is made from that area as WebP, so an image can be re-cropped or resized later.
 *
 *   class Service extends Model implements CroppedImages   // App\Cms\Media\Contracts\CroppedImages
 *   {
 *       use HasCroppedImages;
 *
 *       public function croppedImages(): array { return ['photo' => 'services.photo']; }
 *   }
 *
 *   $service->imageUrl('photo', 'thumb');
 *
 * @mixin Model
 */
trait HasCroppedImages
{
    use InteractsWithMedia;

    public const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * The cropped images: media collection => size key ("{module}.{image}" in module.json).
     *
     * @return array<string, string>
     */
    abstract public function croppedImages(): array;

    public function registerMediaCollections(): void
    {
        foreach (array_keys($this->croppedImages()) as $collection) {
            $this->addMediaCollection($collection)->singleFile()->acceptsMimeTypes(self::IMAGE_MIME_TYPES);
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        foreach ($this->croppedImages() as $collection => $key) {
            // Only the versions of this media's collection, so collections can share version names.
            if ($media !== null && $media->collection_name !== $collection) {
                continue;
            }

            $spec = $this->imageSpec($collection);
            if ($spec === null) {
                continue;
            }

            $crop = CroppedImageInput::cropArea($media?->getCustomProperty('crop'));

            foreach ($spec->versions() as $version => [$width, $height]) {
                $conversion = $this->addMediaConversion($version)
                    ->performOnCollections($collection)
                    ->nonQueued() // shown in the admin right after saving
                    ->format('webp');

                if ($crop !== null) {
                    $conversion->manualCrop($crop['width'], $crop['height'], $crop['x'], $crop['y']);
                }

                // Without a crop area: the centre, in the right proportions.
                $conversion->fit(Fit::Crop, $width, $height);
            }
        }
    }

    public function imageSpec(string $collection): ?ImageSpec
    {
        $key = $this->croppedImages()[$collection] ?? null;

        return $key !== null ? app(ImageSizes::class)->for($key) : null;
    }

    /**
     * The URL of a version ("large", "thumb", …), or null without an image. The ?v= changes when the
     * image is re-cropped or resized, so browsers fetch the new file.
     */
    public function imageUrl(string $collection, string $version = 'large'): ?string
    {
        $media = $this->getFirstMedia($collection);
        if ($media === null) {
            return null;
        }

        $url = $media->hasGeneratedConversion($version) ? $media->getUrl($version) : $media->getUrl();

        return $url.'?v='.$media->updated_at?->getTimestamp();
    }
}
