<?php

namespace App\Cms\Modules;

use App\Cms\Modules\Exceptions\InvalidManifestException;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use JsonException;

/**
 * A module's module.json, read and validated (spec §5.2).
 *
 * {
 *   "name": "Blog", "slug": "blog", "version": "1.0.0",
 *   "description": { "en": "Blog posts", "ru": "Блог" },
 *   "core": false,
 *   "provider": "Modules\\Blog\\Providers\\BlogServiceProvider",
 *   "requires": ["categories"],
 *   "admin_menu": { "group": "content", "icon": "fas fa-newspaper", "order": 20, "label": "blog::admin.menu",
 *                   "items": [ { "label": "blog::admin.posts", "route": "admin.blog.posts.index", "permission": "blog.view" } ] },
 *   "permissions": ["blog.view", "blog.create"],
 *   "widgets": ["Modules\\Blog\\Widgets\\LatestPostsWidget"],
 *   "images": { "cover": { "label": "blog::admin.fields.cover", "width": 1200, "height": 630, "sizes": { "thumb": [600, 315] } } }
 * }
 */
final readonly class Manifest
{
    public const FILE = 'module.json';

    /** Lowercase kebab-case: "blog", "tour-bookings". */
    public const SLUG_PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    /** Semantic version: "1.0.0", "2.1.0-beta.1". */
    public const VERSION_PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/';

    /**
     * @param  string|array<string, string>  $description  plain text or translations by locale
     * @param  list<string>  $requires
     * @param  array<string, mixed>  $adminMenu
     * @param  list<string>  $permissions
     * @param  list<class-string>  $widgets
     * @param  array<string, array{label: string, width: int, height: int, sizes: array<string, array{0: int, 1: int}>}>  $images  cropped image sizes
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $version,
        public string $provider,
        public string $path,
        public string|array $description = '',
        public bool $core = false,
        public array $requires = [],
        public array $adminMenu = [],
        public array $permissions = [],
        public array $widgets = [],
        public array $images = [],
    ) {}

    /**
     * Read and validate a module.json file.
     *
     * @throws InvalidManifestException
     */
    public static function fromFile(string $file): self
    {
        if (! is_file($file) || ! is_readable($file)) {
            throw InvalidManifestException::at($file, 'the file does not exist or is not readable.');
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw InvalidManifestException::at($file, 'invalid JSON ('.$e->getMessage().').');
        }

        if (! is_array($data) || array_is_list($data)) {
            throw InvalidManifestException::at($file, 'the manifest must be a JSON object.');
        }

        return self::fromArray($data, dirname($file));
    }

    /**
     * Build a manifest from decoded module.json data, for the module folder at $path.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidManifestException
     */
    public static function fromArray(array $data, string $path): self
    {
        $file = $path.DIRECTORY_SEPARATOR.self::FILE;
        $fail = fn (string $problem) => throw InvalidManifestException::at($file, $problem);

        foreach (['name', 'slug', 'version', 'provider'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key]) || trim($data[$key]) === '') {
                $fail("\"{$key}\" is required and must be a non-empty string.");
            }
        }

        /** @var string $slug */
        $slug = $data['slug'];
        if (strlen($slug) > 64 || ! preg_match(self::SLUG_PATTERN, $slug)) {
            $fail("\"slug\" must be lowercase kebab-case (e.g. \"blog\" or \"tour-bookings\"), got \"{$slug}\".");
        }

        $folder = basename($path);
        if (Str::kebab($folder) !== $slug) {
            $fail("the folder \"{$folder}\" does not match the slug \"{$slug}\" (expected the folder \"".Str::studly($slug).'").');
        }

        /** @var string $version */
        $version = $data['version'];
        if (! preg_match(self::VERSION_PATTERN, $version)) {
            $fail("\"version\" must be a semantic version like \"1.0.0\", got \"{$version}\".");
        }

        /** @var string $provider */
        $provider = $data['provider'];
        if (! class_exists($provider)) {
            $fail("the provider class \"{$provider}\" does not exist (check the namespace and run composer dump-autoload).");
        }
        if (! is_subclass_of($provider, ServiceProvider::class)) {
            $fail("the provider \"{$provider}\" must extend ".ServiceProvider::class.'.');
        }

        $description = $data['description'] ?? '';
        if (! is_string($description) && ! (is_array($description) && self::isStringMap($description))) {
            $fail('"description" must be a string or an object of translations by locale.');
        }

        $core = $data['core'] ?? false;
        if (! is_bool($core)) {
            $fail('"core" must be true or false.');
        }

        $requires = self::stringList($data, 'requires', $fail);
        foreach ($requires as $required) {
            if (! preg_match(self::SLUG_PATTERN, $required)) {
                $fail("\"requires\" must contain module slugs, got \"{$required}\".");
            }
            if ($required === $slug) {
                $fail('a module cannot require itself.');
            }
        }

        $permissions = self::stringList($data, 'permissions', $fail);
        foreach ($permissions as $permission) {
            if (! preg_match('/^'.preg_quote($slug, '/').'\.[a-z0-9_.-]+$/', $permission)) {
                $fail("permission \"{$permission}\" must be named \"{$slug}.{action}\".");
            }
        }

        $widgets = self::stringList($data, 'widgets', $fail);

        $adminMenu = $data['admin_menu'] ?? [];
        if (! is_array($adminMenu) || ($adminMenu !== [] && array_is_list($adminMenu))) {
            $fail('"admin_menu" must be an object.');
        }
        /** @var array<string, mixed> $adminMenu */
        self::validateAdminMenu($adminMenu, $fail);

        $images = self::validateImages($data['images'] ?? [], $fail);

        /** @var string|array<string, string> $description */
        /** @var list<class-string> $widgets */
        return new self(
            name: $data['name'],
            slug: $slug,
            version: $version,
            provider: $provider,
            path: $path,
            description: $description,
            core: $core,
            requires: array_values(array_unique($requires)),
            adminMenu: $adminMenu,
            permissions: array_values(array_unique($permissions)),
            widgets: $widgets,
            images: $images,
        );
    }

    /**
     * "images": { "photo": { "label": "services::admin.fields.photo", "width": 1200, "height": 800,
     *                        "sizes": { "thumb": [600, 400] } } }
     *
     * @param  callable(string): never  $fail
     * @return array<string, array{label: string, width: int, height: int, sizes: array<string, array{0: int, 1: int}>}>
     */
    private static function validateImages(mixed $images, callable $fail): array
    {
        if (! is_array($images) || ($images !== [] && array_is_list($images))) {
            $fail('"images" must be an object: { "photo": { "width": 1200, "height": 800 } }.');
        }

        $valid = [];
        $isSize = fn (mixed $value) => is_int($value) && $value >= 16 && $value <= 6000;

        foreach ($images as $name => $image) {
            if (! is_string($name) || ! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                $fail("image names must be lowercase identifiers (e.g. \"photo\"), got \"{$name}\".");
            }
            if (! is_array($image) || ! $isSize($image['width'] ?? null) || ! $isSize($image['height'] ?? null)) {
                $fail("\"images.{$name}\" needs a \"width\" and a \"height\" in pixels (16–6000).");
            }

            $sizes = $image['sizes'] ?? [];
            if (! is_array($sizes) || ($sizes !== [] && array_is_list($sizes))) {
                $fail("\"images.{$name}.sizes\" must be an object: { \"thumb\": [600, 400] }.");
            }
            foreach ($sizes as $size => $dimensions) {
                if (! is_string($size) || ! preg_match('/^[a-z][a-z0-9_]*$/', $size) || $size === 'large'
                    || ! is_array($dimensions) || count($dimensions) !== 2 || ! $isSize($dimensions[0] ?? null) || ! $isSize($dimensions[1] ?? null)) {
                    $fail("\"images.{$name}.sizes.{$size}\" must be [width, height] in pixels, named other than \"large\".");
                }
            }

            /** @var array<string, array{0: int, 1: int}> $sizes */
            $valid[$name] = [
                'label' => is_string($image['label'] ?? null) ? $image['label'] : Str::headline($name),
                'width' => (int) $image['width'],
                'height' => (int) $image['height'],
                'sizes' => $sizes,
            ];
        }

        return $valid;
    }

    /**
     * The module's folder, e.g. C:\wamp64\www\odincms\cms\modules\Blog.
     */
    public function path(string $append = ''): string
    {
        return $append === '' ? $this->path : $this->path.DIRECTORY_SEPARATOR.ltrim($append, '\\/');
    }

    /**
     * The folder name (StudlyCase), which is also the namespace segment: Modules\{folder}.
     */
    public function folder(): string
    {
        return basename($this->path);
    }

    public function namespace(): string
    {
        return 'Modules\\'.$this->folder();
    }

    public function isCore(): bool
    {
        return $this->core;
    }

    /**
     * Slugs of the modules that must be installed and enabled first.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return $this->requires;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /**
     * @return list<class-string>
     */
    public function widgets(): array
    {
        return $this->widgets;
    }

    /**
     * The module's cropped images and their default sizes (see ImageSizes).
     *
     * @return array<string, array{label: string, width: int, height: int, sizes: array<string, array{0: int, 1: int}>}>
     */
    public function images(): array
    {
        return $this->images;
    }

    /**
     * @return array<string, mixed>
     */
    public function adminMenu(): array
    {
        return $this->adminMenu;
    }

    /**
     * The description in $locale, falling back to the default locale, then to any translation.
     */
    public function description(?string $locale = null): string
    {
        if (is_string($this->description)) {
            return $this->description;
        }

        $locale ??= app()->getLocale();

        return $this->description[$locale]
            ?? $this->description[(string) config('cms.default_locale')]
            ?? array_values($this->description)[0]
            ?? '';
    }

    /**
     * @param  array<mixed>  $value
     */
    private static function isStringMap(array $value): bool
    {
        foreach ($value as $key => $item) {
            if (! is_string($key) || ! is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  callable(string): never  $fail
     * @return list<string>
     */
    private static function stringList(array $data, string $key, callable $fail): array
    {
        $value = $data[$key] ?? [];

        if (! is_array($value) || ! array_is_list($value)) {
            $fail("\"{$key}\" must be an array of strings.");
        }

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                $fail("\"{$key}\" must be an array of non-empty strings.");
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $menu
     * @param  callable(string): never  $fail
     */
    private static function validateAdminMenu(array $menu, callable $fail): void
    {
        if ($menu === []) {
            return;
        }

        foreach (['group', 'icon', 'label'] as $key) {
            if (isset($menu[$key]) && ! is_string($menu[$key])) {
                $fail("\"admin_menu.{$key}\" must be a string.");
            }
        }

        if (isset($menu['order']) && ! is_int($menu['order'])) {
            $fail('"admin_menu.order" must be an integer.');
        }

        $items = $menu['items'] ?? [];
        if (! is_array($items) || ! array_is_list($items)) {
            $fail('"admin_menu.items" must be an array.');
        }

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                $fail("\"admin_menu.items.{$i}\" must be an object.");
            }
            foreach (['label', 'route'] as $key) {
                if (! isset($item[$key]) || ! is_string($item[$key]) || $item[$key] === '') {
                    $fail("\"admin_menu.items.{$i}.{$key}\" is required and must be a string.");
                }
            }
            if (isset($item['permission']) && ! is_string($item['permission'])) {
                $fail("\"admin_menu.items.{$i}.permission\" must be a string.");
            }
        }
    }
}
