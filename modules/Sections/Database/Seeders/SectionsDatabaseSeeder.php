<?php

namespace Modules\Sections\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Widgets\Models\WidgetArea;
use Modules\Widgets\Models\WidgetInstance;

/**
 * Runs when the module is installed: puts "Why choose us" and then the marquee at the end of the current
 * theme's "home" area, each only when that area has no instance of it yet. Does nothing without the area.
 * (Widgets is a declared dependency, "requires" in module.json.)
 */
class SectionsDatabaseSeeder extends Seeder
{
    public const AREA = 'home';

    public function run(): void
    {
        $area = WidgetArea::where('theme', config('cms.theme'))->where('slug', self::AREA)->first();
        if ($area === null) {
            return;
        }

        foreach ($this->widgets() as $widget => $attributes) {
            if (WidgetInstance::where('area_id', $area->id)->where('widget', $widget)->exists()) {
                continue;
            }

            WidgetInstance::create([
                ...$attributes,
                'area_id' => $area->id,
                'widget' => $widget,
                'sort_order' => (int) WidgetInstance::where('area_id', $area->id)->max('sort_order') + 1,
                'is_active' => true,
                'cache_ttl' => 3600,
            ]);
        }
    }

    /**
     * @return array<string, array<string, mixed>> widget key => instance attributes
     */
    private function widgets(): array
    {
        return [
            'sections.why-choose-us' => [
                'title' => ['en' => 'Why choose us', 'ru' => 'Почему мы', 'ky' => 'Эмне үчүн биз'],
                'settings' => [
                    'slogan' => [
                        'en' => 'Fast, secure websites built to grow your business',
                        'ru' => 'Быстрые и надёжные сайты, которые помогают бизнесу расти',
                        'ky' => 'Бизнесиңиздин өсүшүнө жардам берген тез жана ишенимдүү сайттар',
                    ],
                    'intro' => [
                        'en' => "Senior developers, fixed estimates and weekly demos. You always see what we're building, why, and when it ships.",
                        'ru' => 'Опытные разработчики, фиксированная смета и демо каждую неделю. Вы всегда видите, что мы делаем, зачем и когда запуск.',
                        'ky' => 'Тажрыйбалуу иштеп чыгуучулар, белгиленген смета жана ар жумалык демо. Эмне кылып жатканыбызды, эмне үчүн жана качан ишке киргизерибизди дайыма көрүп турасыз.',
                    ],
                    'badge' => ['en' => 'Laravel experts', 'ru' => 'Эксперты Laravel', 'ky' => 'Laravel адистери'],
                    'card_1_icon' => 'code-slash',
                    'card_1_title' => ['en' => 'Custom Development', 'ru' => 'Разработка под задачу', 'ky' => 'Тапшырмага ылайык иштеп чыгуу'],
                    'card_1_text' => [
                        'en' => 'Laravel and modern front-ends built around your workflow, not squeezed into a template.',
                        'ru' => 'Laravel и современный фронтенд под ваши процессы, а не подгонка под шаблон.',
                        'ky' => 'Шаблонго ылайыкташтыруу эмес, сиздин иш процесстериңизге курулган Laravel жана заманбап фронтенд.',
                    ],
                    'card_2_icon' => 'speedometer2',
                    'card_2_title' => ['en' => 'Performance & SEO', 'ru' => 'Скорость и SEO', 'ky' => 'Ылдамдык жана SEO'],
                    'card_2_text' => [
                        'en' => 'Green Core Web Vitals, clean markup and structured data that search engines reward.',
                        'ru' => 'Зелёные Core Web Vitals, чистая вёрстка и микроразметка, которую ценят поисковики.',
                        'ky' => 'Жашыл Core Web Vitals, таза белгилөө жана издөө системалары баалаган структураланган маалыматтар.',
                    ],
                    'bar_label' => ['en' => 'Projects launched on schedule', 'ru' => 'Проекты, сданные в срок', 'ky' => 'Убагында тапшырылган долбоорлор'],
                    'bar_value' => 97,
                    'link_label' => ['en' => 'Start a project', 'ru' => 'Обсудить проект', 'ky' => 'Долбоорду талкуулоо'],
                    'link_url' => '/contact',
                ],
            ],
            'sections.marquee' => [
                'title' => ['en' => 'Marquee', 'ru' => 'Бегущая строка', 'ky' => 'Жылма сап'],
                'settings' => [
                    'words' => [
                        'en' => implode("\n", ['Design', 'Development', 'E-commerce', 'Performance', 'SEO', 'Security', 'Support']),
                        'ru' => implode("\n", ['Дизайн', 'Разработка', 'E-commerce', 'Скорость', 'SEO', 'Безопасность', 'Поддержка']),
                        'ky' => implode("\n", ['Дизайн', 'Иштеп чыгуу', 'E-commerce', 'Ылдамдык', 'SEO', 'Коопсуздук', 'Колдоо']),
                    ],
                    'speed' => 40,
                    'tilt' => true,
                    'direction' => 'left',
                ],
            ],
        ];
    }
}
