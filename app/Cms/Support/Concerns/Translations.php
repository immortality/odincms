<?php

namespace App\Cms\Support\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Helpers for the content traits, which work with and without spatie/laravel-translatable.
 */
final class Translations
{
    /**
     * Is $attribute a translatable (JSON, per-locale) attribute of $model?
     */
    public static function isTranslatable(Model $model, string $attribute): bool
    {
        return method_exists($model, 'isTranslatableAttribute') && $model->isTranslatableAttribute($attribute);
    }
}
