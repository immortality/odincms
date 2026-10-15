<?php

namespace Tests\Fixtures\Widgets;

use App\Cms\Widgets\Widget;
use RuntimeException;

class BrokenWidget extends Widget
{
    public static string $key = 'test.broken';

    public static string $name = 'Broken';

    public function render(): string
    {
        throw new RuntimeException('Something went wrong');
    }
}
