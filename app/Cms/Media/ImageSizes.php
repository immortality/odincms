<?php

namespace App\Cms\Media;

use App\Cms\Modules\ModuleManager;
use App\Cms\Settings\Settings;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * The cropped image sizes of the enabled modules (module.json → "images"), which Admin → Settings →
 * Images can override. A changed size re-creates the existing images of that kind from their
 * originals and crop areas.
 *
 * Keys are "{module}.{image}", e.g. "services.photo"; the setting is "images.services-photo" = "1200x800".
 */
class ImageSizes
{
    public const SETTINGS_SECTION = 'images';

    public const SIZE_PATTERN = '/^(\d{2,4})x(\d{2,4})$/';

    public function __construct(
        private ModuleManager $modules,
        private Settings $settings,
    ) {}

    /**
     * @return array<string, ImageSpec> key => spec
     */
    public function all(): array
    {
        $specs = [];

        try {
            $manifests = $this->modules->enabledManifests();
        } catch (Throwable) {
            return []; // no database yet
        }

        foreach ($manifests as $slug => $manifest) {
            foreach ($manifest->images() as $name => $image) {
                $specs["{$slug}.{$name}"] = $this->build("{$slug}.{$name}", $image);
            }
        }

        return $specs;
    }

    public function for(string $key): ?ImageSpec
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Add the "Images" section to Admin → Settings: one "width x height" field per image.
     */
    public function registerSettings(): void
    {
        $fields = [];

        foreach ($this->manifestDefaults() as $key => $image) {
            $fields[self::settingName($key)] = [
                'type' => 'text',
                'label' => $image['label'],
                'help' => 'cms.images.size_help',
                'default' => $image['width'].'x'.$image['height'],
                'rules' => ['required', 'regex:'.self::SIZE_PATTERN],
            ];
        }

        if ($fields !== []) {
            $this->settings->section(self::SETTINGS_SECTION, 'cms.images.section', $fields, icon: 'far fa-image', order: 80);
        }
    }

    /**
     * Re-create every image of $key in its current sizes (after a size change).
     *
     * @return int the number of images re-created
     */
    public function regenerate(string $key): int
    {
        $count = 0;
        CroppedImageInput::ensureMemory();

        Media::query()->where('custom_properties->image_spec', $key)->each(function (Media $media) use (&$count) {
            try {
                app(FileManipulator::class)->createDerivedFiles($media);
                $media->touch(); // new URLs (?v=…), so browsers don't show the old size
                $count++;
            } catch (Throwable $e) {
                Log::warning("OdinCMS: could not re-create image #{$media->id}: ".$e->getMessage());
            }
        });

        return $count;
    }

    /**
     * The setting name of an image: "services.photo" → "services-photo".
     */
    public static function settingName(string $key): string
    {
        return str_replace('.', '-', $key);
    }

    /**
     * @param  array{label: string, width: int, height: int, sizes: array<string, array{0: int, 1: int}>}  $image
     */
    private function build(string $key, array $image): ImageSpec
    {
        $width = $image['width'];
        $height = $image['height'];
        $sizes = $image['sizes'];

        $setting = $this->settings->get(self::SETTINGS_SECTION.'.'.self::settingName($key));
        if (is_string($setting) && preg_match(self::SIZE_PATTERN, $setting, $match) && [(int) $match[1], (int) $match[2]] !== [$width, $height]) {
            [$width, $height] = [(int) $match[1], (int) $match[2]];

            // The smaller versions keep their width and take the new proportions.
            $sizes = array_map(fn (array $size) => [$size[0], (int) round($size[0] * $height / $width)], $sizes);
        }

        return new ImageSpec($key, $image['label'], $width, $height, $sizes);
    }

    /**
     * @return array<string, array{label: string, width: int, height: int, sizes: array<string, array{0: int, 1: int}>}>
     */
    private function manifestDefaults(): array
    {
        $images = [];

        try {
            foreach ($this->modules->enabledManifests() as $slug => $manifest) {
                foreach ($manifest->images() as $name => $image) {
                    $images["{$slug}.{$name}"] = $image;
                }
            }
        } catch (Throwable) {
            // no database yet
        }

        return $images;
    }
}
