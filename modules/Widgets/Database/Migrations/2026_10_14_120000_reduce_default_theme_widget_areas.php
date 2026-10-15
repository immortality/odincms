<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widgets 1.1.0: the default theme has three areas — "hero", "home", "sidebar". On an existing site
 * "home-hero" becomes "hero" and "home-top" becomes "home" (their widgets stay), and the footer
 * columns ("footer-1..3") are removed with their widgets. A fresh install gets the areas from the seeder.
 */
return new class extends Migration
{
    private const NAMES = [
        'hero' => ['en' => 'Hero section', 'ru' => 'Первый экран', 'ky' => 'Биринчи экран'],
        'home' => ['en' => 'Home page', 'ru' => 'Главная страница', 'ky' => 'Башкы бет'],
    ];

    public function up(): void
    {
        $areas = DB::table('widget_areas')->where('theme', 'default');

        foreach (['home-hero' => 'hero', 'home-top' => 'home'] as $old => $new) {
            if (! (clone $areas)->where('slug', $new)->exists()) {
                (clone $areas)->where('slug', $old)->update(['slug' => $new, 'name' => json_encode(self::NAMES[$new], JSON_UNESCAPED_UNICODE)]);
            }
        }

        // Their widgets go with them (widget_instances.area_id cascades).
        (clone $areas)->whereIn('slug', ['footer-1', 'footer-2', 'footer-3'])->delete();
    }

    public function down(): void
    {
        $areas = DB::table('widget_areas')->where('theme', 'default');

        (clone $areas)->where('slug', 'hero')->update(['slug' => 'home-hero']);
        (clone $areas)->where('slug', 'home')->update(['slug' => 'home-top']);
    }
};
