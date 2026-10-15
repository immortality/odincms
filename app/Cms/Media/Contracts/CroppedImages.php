<?php

namespace App\Cms\Media\Contracts;

use App\Cms\Media\ImageSpec;
use Spatie\MediaLibrary\HasMedia;

/**
 * A model with cropped images. Implement it with the HasCroppedImages trait:
 *
 *   class Service extends Model implements CroppedImages
 *   {
 *       use HasCroppedImages;
 *
 *       public function croppedImages(): array { return ['photo' => 'services.photo']; }
 *   }
 */
interface CroppedImages extends HasMedia
{
    /**
     * Media collection => size key ("{module}.{image}" in module.json → "images").
     *
     * @return array<string, string>
     */
    public function croppedImages(): array;

    public function imageSpec(string $collection): ?ImageSpec;

    public function imageUrl(string $collection, string $version = 'large'): ?string;
}
