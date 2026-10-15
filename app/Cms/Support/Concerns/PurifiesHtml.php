<?php

namespace App\Cms\Support\Concerns;

use App\Cms\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Cleans the HTML of WYSIWYG attributes on save (spec §12) with HtmlSanitizer: the design is kept
 * (classes, ids, data-*, styles, inline SVG, icon tags, https iframes); scripts, event handlers and
 * javascript: links are removed. Works for plain and translatable attributes, so content is safe however
 * it is saved (admin form, import, tinker).
 *
 * protected function purifiedAttributes(): array { return ['body']; }
 *
 * @mixin Model
 */
trait PurifiesHtml
{
    /**
     * @return list<string>
     */
    abstract protected function purifiedAttributes(): array;

    public static function bootPurifiesHtml(): void
    {
        static::saving(function (Model $model) {
            /** @var Model&self $model */
            $model->purifyHtmlAttributes();
        });
    }

    public function purifyHtmlAttributes(): void
    {
        foreach ($this->purifiedAttributes() as $attribute) {
            if (! $this->isDirty($attribute)) {
                continue;
            }

            if (Translations::isTranslatable($this, $attribute)) {
                $translations = $this->getTranslations($attribute);
                $this->setAttribute($attribute, array_map(fn ($html) => self::cleanHtml($html), $translations));
            } else {
                $this->setAttribute($attribute, self::cleanHtml($this->getAttribute($attribute)));
            }
        }
    }

    private static function cleanHtml(mixed $html): ?string
    {
        return is_string($html) && $html !== '' ? HtmlSanitizer::clean($html) : (is_string($html) ? $html : null);
    }
}
