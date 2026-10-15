<?php

namespace Modules\Categories\Support;

use Closure;
use InvalidArgumentException;
use Modules\Categories\Models\Category;

/**
 * The category types of the enabled modules. A module that uses categories declares
 * "requires": ["categories"] and registers its type from its provider's boot():
 *
 *   app(CategoryTypes::class)->register('blog', 'blog::admin.categories', fn (Category $category) => route('blog.category', $category->slug));
 *
 * The URL callback makes the type's categories linkable from menus. A disabled module doesn't
 * register its type, so its categories drop out of the admin and the link picker.
 */
class CategoryTypes
{
    public const PATTERN = '/^[a-z][a-z0-9-]{0,31}$/';

    /** @var array<string, array{label: string, url: (Closure(Category): string)|null}> */
    private array $types = [];

    /**
     * @param  (Closure(Category): string)|null  $url  the public URL of a category of this type
     */
    public function register(string $type, string $label, ?Closure $url = null): void
    {
        if (! preg_match(self::PATTERN, $type)) {
            throw new InvalidArgumentException("Invalid category type \"{$type}\": use lowercase letters, digits and dashes.");
        }

        $this->types[$type] = ['label' => $label, 'url' => $url];
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * @return array<string, string> type => translated label
     */
    public function all(): array
    {
        return array_map(fn (array $type) => (string) __($type['label']), $this->types);
    }

    public function label(string $type): string
    {
        return isset($this->types[$type]) ? (string) __($this->types[$type]['label']) : $type;
    }

    /**
     * Types whose categories have a public URL.
     *
     * @return list<string>
     */
    public function linkable(): array
    {
        return array_keys(array_filter($this->types, fn (array $type) => $type['url'] !== null));
    }

    public function url(Category $category): ?string
    {
        $url = $this->types[$category->type]['url'] ?? null;

        return $url?->__invoke($category);
    }
}
