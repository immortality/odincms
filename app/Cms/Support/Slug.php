<?php

namespace App\Cms\Support;

use Illuminate\Support\Str;

/**
 * URL slugs: latin letters, digits and dashes ("Иссык-Куль летом" → "issyk-kul-letom").
 */
final class Slug
{
    public const MAX_LENGTH = 190;

    /** For validation rules: a normalized slug. */
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public static function make(string $text): string
    {
        return trim(Str::limit(Str::slug($text), self::MAX_LENGTH, ''), '-');
    }
}
