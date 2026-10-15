<?php

namespace Modules\Banners\Widgets;

use App\Cms\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Modules\Banners\Models\Banner;

/**
 * Several banners as a hero slider, in their "Position" order. Drafts are skipped; with only one
 * published banner left it is shown like the Hero banner widget.
 */
class SliderWidget extends Widget
{
    public static string $key = 'banners.slider';

    public static string $name = 'banners::widgets.slider.name';

    public static string $description = 'banners::widgets.slider.description';

    public static string $icon = 'fas fa-images';

    public function fields(): array
    {
        return [
            'banners' => [
                'type' => 'multiselect',
                'label' => 'banners::widgets.banners',
                'options' => fn () => Banner::options(),
                'help' => 'banners::widgets.slider.banners_help',
                'rules' => ['required', 'min:1'],
            ],
            'interval' => [
                'type' => 'number',
                'label' => 'banners::widgets.slider.interval',
                'help' => 'banners::widgets.slider.interval_help',
                'default' => 6,
                'rules' => ['required', 'integer', 'min:0', 'max:60'],
            ],
            'arrows' => [
                'type' => 'checkbox',
                'label' => 'banners::widgets.slider.arrows',
                'default' => true,
            ],
            'dots' => [
                'type' => 'checkbox',
                'label' => 'banners::widgets.slider.dots',
                'default' => true,
            ],
        ];
    }

    public function render(): View|string
    {
        $ids = array_map('intval', (array) $this->setting('banners'));

        $banners = $ids === [] ? collect() : Banner::published()->with('media')->whereKey($ids)->ordered()->get();

        return match ($banners->count()) {
            0 => '',
            1 => view($this->themeView('banners', 'hero'), ['banner' => $banners->first()]),
            default => view($this->themeView('banners', 'slider'), [
                'banners' => $banners,
                'interval' => (int) $this->setting('interval'),
                'arrows' => (bool) $this->setting('arrows'),
                'dots' => (bool) $this->setting('dots'),
            ]),
        };
    }
}
