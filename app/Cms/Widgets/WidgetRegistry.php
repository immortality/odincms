<?php

namespace App\Cms\Widgets;

use InvalidArgumentException;

/**
 * The widget types that can be used: the core widgets and those of the enabled modules
 * (registered by ModuleServiceProvider from each manifest's "widgets"). Instances of a widget
 * whose module is disabled find no type here and are skipped when rendering.
 */
class WidgetRegistry
{
    /** @var array<string, class-string<Widget>> key => class */
    private array $types = [];

    /**
     * @param  class-string  $class
     */
    public function register(string $class): void
    {
        if (! is_subclass_of($class, Widget::class)) {
            throw new InvalidArgumentException("{$class} must extend ".Widget::class.'.');
        }

        $key = $class::$key;
        if (! preg_match('/^[a-z][a-z0-9-]*\.[a-z0-9][a-z0-9.-]*$/', $key)) {
            throw new InvalidArgumentException("Widget key \"{$key}\" of {$class} must look like \"{module}.{name}\".");
        }

        $this->types[$key] = $class;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * @return class-string<Widget>|null
     */
    public function get(string $key): ?string
    {
        return $this->types[$key] ?? null;
    }

    /**
     * A widget of type $key with the given settings, or null when the type is not available.
     *
     * @param  array<string, mixed>  $settings
     */
    public function make(string $key, array $settings = []): ?Widget
    {
        $class = $this->get($key);

        return $class === null ? null : new $class($settings);
    }

    /**
     * @return array<string, class-string<Widget>> key => class, sorted by name
     */
    public function all(): array
    {
        $types = $this->types;
        uasort($types, fn (string $a, string $b) => strcmp($a::label(), $b::label()));

        return $types;
    }
}
