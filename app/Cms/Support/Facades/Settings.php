<?php

namespace App\Cms\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static string translated(string $key, ?string $locale = null, string $default = '')
 * @method static void set(string $key, mixed $value)
 * @method static void setMany(array<string, mixed> $values)
 * @method static void forget(string $key)
 * @method static array<string, mixed> all()
 * @method static void section(string $key, string $label, array<string, array<string, mixed>> $fields, ?string $module = null, string $icon = 'fas fa-sliders-h', int $order = 50)
 * @method static array<string, array<string, mixed>> sections()
 *
 * @see \App\Cms\Settings\Settings
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Cms\Settings\Settings::class;
    }
}
