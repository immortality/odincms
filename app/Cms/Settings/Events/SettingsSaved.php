<?php

namespace App\Cms\Settings\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Settings were saved (Settings::setMany, e.g. a section of Admin → Settings). Only the values that
 * actually changed are listed, so listeners can react to their own keys (e.g. image sizes).
 */
class SettingsSaved
{
    use Dispatchable;

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes  key => old and new value
     */
    public function __construct(public array $changes) {}

    /**
     * The changed keys that start with $prefix (e.g. "images.").
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changed(string $prefix = ''): array
    {
        return array_filter($this->changes, fn (string $key) => str_starts_with($key, $prefix), ARRAY_FILTER_USE_KEY);
    }
}
