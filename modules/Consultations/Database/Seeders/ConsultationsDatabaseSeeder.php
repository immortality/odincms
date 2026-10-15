<?php

namespace Modules\Consultations\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Widgets\Models\WidgetArea;
use Modules\Widgets\Models\WidgetInstance;

/**
 * Runs when the module is installed: appends the "Request a free consultation" widget to the current theme's
 * "home" area when it is not there yet. Does nothing without the area. (Widgets is a declared dependency.)
 */
class ConsultationsDatabaseSeeder extends Seeder
{
    public const AREA = 'home';

    public const WIDGET = 'consultations.form';

    public function run(): void
    {
        $area = WidgetArea::where('theme', config('cms.theme'))->where('slug', self::AREA)->first();
        if ($area === null || WidgetInstance::where('area_id', $area->id)->where('widget', self::WIDGET)->exists()) {
            return;
        }

        WidgetInstance::create([
            'area_id' => $area->id,
            'widget' => self::WIDGET,
            'title' => [],
            'settings' => [
                'stat_value' => '40+',
                'stat_label' => [
                    'en' => 'Trusted, happy & satisfied businesses',
                    'ru' => 'Довольных компаний доверяют нам',
                    'ky' => 'Ыраазы компаниялар бизге ишенет',
                ],
                'slogan' => [
                    'en' => "Let's build your next growth chapter together",
                    'ru' => 'Давайте вместе напишем следующую главу вашего роста',
                    'ky' => 'Өсүшүңүздүн кийинки баскычын бирге куралы',
                ],
                'intro' => [
                    'en' => 'Tell us about your idea and we will suggest the right approach, timeline and budget — from discovery to launch.',
                    'ru' => 'Расскажите о своей идее, и мы предложим подход, сроки и бюджет — от исследования до запуска.',
                    'ky' => 'Идеяңыз жөнүндө айтып бериңиз, биз ылайыктуу ыкманы, мөөнөттү жана бюджетти сунуштайбыз — изилдөөдөн ишке киргизүүгө чейин.',
                ],
                'form_title' => [],
                'button' => [],
            ],
            'sort_order' => (int) WidgetInstance::where('area_id', $area->id)->max('sort_order') + 1,
            'is_active' => true,
            'cache_ttl' => null,
        ]);
    }
}
