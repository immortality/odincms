<?php

namespace App\Cms\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The Linkable models that enabled modules offer to navigation links (spec §10). Modules register
 * their models from their provider; disabled modules don't, so their models drop out of the picker.
 *
 * Saving or deleting a registered model clears the CMS cache, so cached menus pick up the new URL.
 */
class LinkableRegistry
{
    /** @var array<class-string<Model&Linkable>, string> class => label (translation key or text) */
    private array $types = [];

    public function __construct(private CmsCache $cache) {}

    /**
     * @param  class-string  $class
     */
    public function register(string $class, string $label): void
    {
        if (! is_subclass_of($class, Model::class) || ! is_subclass_of($class, Linkable::class)) {
            throw new InvalidArgumentException("{$class} must be an Eloquent model that implements ".Linkable::class.'.');
        }

        if (! isset($this->types[$class])) {
            $class::saved(fn () => $this->cache->flush());
            $class::deleted(fn () => $this->cache->flush());
        }

        $this->types[$class] = $label;
    }

    /**
     * @return array<class-string<Model&Linkable>, string> class => translated label
     */
    public function types(): array
    {
        return array_map(fn (string $label) => (string) __($label), $this->types);
    }

    public function has(string $class): bool
    {
        return isset($this->types[$class]);
    }
}
