<?php

namespace Tests\Fixtures\Widgets;

use App\Cms\Widgets\Widget;
use Illuminate\Support\HtmlString;

class GreetingWidget extends Widget
{
    public static string $key = 'test.greeting';

    public static string $name = 'Greeting';

    public static int $renders = 0;

    public function fields(): array
    {
        return [
            'name' => ['type' => 'text', 'label' => 'Name', 'default' => 'World', 'rules' => ['required', 'max:20']],
            'shout' => ['type' => 'checkbox', 'label' => 'Shout'],
        ];
    }

    public function render(): HtmlString
    {
        self::$renders++;
        $text = 'Hello, '.e($this->setting('name')).'!';

        return new HtmlString('<p class="greeting">'.($this->setting('shout') ? strtoupper($text) : $text).'</p>');
    }
}
