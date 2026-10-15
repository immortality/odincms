<?php

namespace Modules\Sections\Widgets;

use App\Cms\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Modules\Sections\Support\SectionContent;

/**
 * A full-width (optionally tilted) ribbon of words scrolling in a loop: one word or phrase per line,
 * alternating solid and outline text.
 */
class MarqueeWidget extends Widget
{
    public static string $key = 'sections.marquee';

    public static string $name = 'sections::widgets.marquee.name';

    public static string $description = 'sections::widgets.marquee.description';

    public static string $icon = 'fas fa-arrows-alt-h';

    /** The ribbon has no heading: the instance title is only its name in the admin. */
    public static bool $ownHeading = true;

    public function fields(): array
    {
        return [
            'words' => [
                'type' => 'translatable',
                'input' => 'textarea',
                'label' => 'sections::widgets.marquee.words',
                'help' => 'sections::widgets.marquee.words_help',
                'required' => true,
                'max' => 500,
                'rows' => 7,
            ],
            'speed' => [
                'type' => 'number',
                'label' => 'sections::widgets.marquee.speed',
                'help' => 'sections::widgets.marquee.speed_help',
                'default' => 40,
                'rules' => ['required', 'integer', 'min:10', 'max:120'],
            ],
            'direction' => [
                'type' => 'select',
                'label' => 'sections::widgets.marquee.direction',
                'options' => fn () => ['left' => __('sections::widgets.marquee.directions.left'), 'right' => __('sections::widgets.marquee.directions.right')],
                'default' => 'left',
                'rules' => ['required'],
            ],
            'tilt' => [
                'type' => 'checkbox',
                'label' => 'sections::widgets.marquee.tilt',
                'default' => true,
            ],
        ];
    }

    public function render(): View|string
    {
        $words = SectionContent::lines($this->translated('words'));

        return $words === [] ? '' : view($this->themeView('sections', 'marquee'), [
            'words' => $words,
            'speed' => max(10, min(120, (int) $this->setting('speed'))),
            'reverse' => $this->setting('direction') === 'right',
            'tilt' => (bool) $this->setting('tilt'),
        ]);
    }
}
