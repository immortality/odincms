<?php

namespace App\Cms\Media;

use App\Cms\Media\Contracts\CroppedImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\Conversions\FileManipulator;

/**
 * The request side of <x-admin.form.cropped-image name="photo">, which posts:
 *   photo         the original file (when a new one was chosen)
 *   photo_crop    the crop area in the original's pixels: {"x":…,"y":…,"width":…,"height":…}
 *   photo_remove  1 to delete the image
 *
 * In the form request:  ...CroppedImageInput::rules('photo'),
 * In the controller:    CroppedImageInput::save($request, $service, 'photo');
 */
final class CroppedImageInput
{
    /** Largest original accepted, in kilobytes. */
    public const MAX_KB = 10240;

    /** PHP memory needed to crop large originals with GD. */
    public const MEMORY = '512M';

    /**
     * @return array<string, list<string>>
     */
    public static function rules(string $collection, bool $required = false): array
    {
        return [
            $collection => [$required ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.self::MAX_KB],
            "{$collection}_crop" => ['nullable', 'json'],
            "{$collection}_remove" => ['boolean'],
        ];
    }

    /**
     * Remove the image, store a new original with its crop area, or re-crop the current image.
     *
     * @param  Model&CroppedImages  $model
     */
    public static function save(Request $request, CroppedImages $model, string $collection): void
    {
        self::ensureMemory();

        $key = $model->croppedImages()[$collection];
        $crop = self::cropArea($request->input("{$collection}_crop"));

        // Removing wins over a file chosen before pressing Remove.
        if ($request->boolean("{$collection}_remove")) {
            $model->clearMediaCollection($collection);

            return;
        }

        if ($request->hasFile($collection)) {
            $model->addMediaFromRequest($collection)
                ->withCustomProperties(['image_spec' => $key, 'crop' => $crop])
                ->toMediaCollection($collection);

            return;
        }

        $media = $model->getFirstMedia($collection);

        // A new crop area for the current image: re-create its versions.
        if ($media !== null && $crop !== null && $crop !== $media->getCustomProperty('crop')) {
            $media->setCustomProperty('crop', $crop)->setCustomProperty('image_spec', $key);
            $media->save();
            app(FileManipulator::class)->createDerivedFiles($media);
        }
    }

    /**
     * GD holds the whole decoded original in memory (a 6000×4000 photo needs about 100 MB), so allow
     * up to MEMORY for this request when PHP's limit is lower.
     */
    public static function ensureMemory(): void
    {
        $limit = (string) ini_get('memory_limit');
        if ($limit !== '-1' && self::bytes($limit) < self::bytes(self::MEMORY)) {
            ini_set('memory_limit', self::MEMORY);
        }
    }

    private static function bytes(string $size): int
    {
        $value = (int) $size;

        return match (strtolower(substr(trim($size), -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * A valid crop area (from the request or the media's custom property): whole pixels of the original.
     *
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    public static function cropArea(mixed $crop): ?array
    {
        if (is_string($crop)) {
            $crop = json_decode($crop, true);
        }

        if (! is_array($crop)) {
            return null;
        }

        foreach (['x', 'y', 'width', 'height'] as $key) {
            if (! is_numeric($crop[$key] ?? null)) {
                return null;
            }
        }

        $area = [
            'x' => max(0, (int) round((float) $crop['x'])),
            'y' => max(0, (int) round((float) $crop['y'])),
            'width' => (int) round((float) $crop['width']),
            'height' => (int) round((float) $crop['height']),
        ];

        return $area['width'] > 0 && $area['height'] > 0 ? $area : null;
    }
}
