<?php

namespace Modules\Widgets\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Widgets\Models\WidgetArea;

/**
 * Runs when the module is installed: the widget areas of the default theme. "hero" is the top of the
 * home page (a Hero banner or a Banner slider), "home" holds the home page's sections in their order,
 * "sidebar" is the column next to the blog and the "sidebar" page layout.
 */
class WidgetsDatabaseSeeder extends Seeder
{
    public const AREAS = [
        'hero' => ['en' => 'Hero section', 'ru' => 'Первый экран', 'ky' => 'Биринчи экран'],
        'home' => ['en' => 'Home page', 'ru' => 'Главная страница', 'ky' => 'Башкы бет'],
        'sidebar' => ['en' => 'Sidebar', 'ru' => 'Боковая колонка', 'ky' => 'Каптал тилке'],
    ];

    public function run(): void
    {
        foreach (self::AREAS as $slug => $name) {
            WidgetArea::firstOrCreate(['theme' => 'default', 'slug' => $slug], ['name' => $name]);
        }
    }
}
