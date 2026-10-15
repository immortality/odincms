<?php

namespace Modules\Navigation\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Navigation\Models\NavigationGroup;

/**
 * Runs when the module is installed: the header and footer menus, with a Home link in the header.
 */
class NavigationDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $header = NavigationGroup::firstOrCreate(['slug' => 'header'], [
            'title' => ['en' => 'Header menu', 'ru' => 'Меню в шапке', 'ky' => 'Баш жагындагы меню'],
        ]);

        NavigationGroup::firstOrCreate(['slug' => 'footer'], [
            'title' => ['en' => 'Footer menu', 'ru' => 'Меню в подвале', 'ky' => 'Төмөнкү меню'],
        ]);

        if (! $header->links()->exists()) {
            $header->links()->create([
                'type' => 'route',
                'route_name' => 'home',
                'title' => ['en' => 'Home', 'ru' => 'Главная', 'ky' => 'Башкы бет'],
                'sort_order' => 1,
            ]);
        }
    }
}
