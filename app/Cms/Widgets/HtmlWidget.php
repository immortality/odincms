<?php

namespace App\Cms\Widgets;

use Illuminate\Support\HtmlString;

/**
 * A free text/HTML block in each language (core widget). The HTML is purified when it is saved.
 */
class HtmlWidget extends Widget
{
    public static string $key = 'core.html';

    public static string $name = 'Text / HTML';

    public static string $description = 'A block of formatted text in each language.';

    public static string $icon = 'fas fa-code';

    public function fields(): array
    {
        return [
            'content' => [
                'type' => 'translatable',
                'input' => 'editor',
                'label' => 'Content',
            ],
        ];
    }

    public function render(): HtmlString
    {
        return new HtmlString($this->translated('content'));
    }
}
