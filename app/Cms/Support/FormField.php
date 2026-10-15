<?php

namespace App\Cms\Support;

/**
 * Helpers for the admin form components (resources/views/components/admin/form).
 */
class FormField
{
    /**
     * Convert an input name to its validation / old-input key: "title[en]" → "title.en", "tags[]" → "tags".
     */
    public static function key(string $name): string
    {
        return trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    }

    /**
     * A predictable element id for an input name: "title[en]" → "field-title-en".
     */
    public static function id(string $name): string
    {
        return 'field-'.str_replace('.', '-', self::key($name));
    }
}
