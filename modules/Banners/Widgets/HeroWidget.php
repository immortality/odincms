<?php

namespace Modules\Banners\Widgets;

use App\Cms\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Modules\Banners\Models\Banner;

/**
 * One banner as the hero section. Shows nothing while the banner is a draft or deleted.
 */
class HeroWidget extends Widget
{
    public static string $key = 'banners.hero';

    public static string $name = 'banners::widgets.hero.name';

    public static string $description = 'banners::widgets.hero.description';

    public static string $icon = 'fas fa-image';

    public function fields(): array
    {
        return [
            'banner' => [
                'type' => 'select',
                'label' => 'banners::widgets.banner',
                'options' => fn () => Banner::options(),
                'placeholder' => 'banners::widgets.choose',
                'rules' => ['required'],
            ],
        ];
    }

    public function render(): View|string
    {
        $banner = Banner::published()->with('media')->find((int) $this->setting('banner'));

        return $banner === null ? '' : view($this->themeView('banners', 'hero'), ['banner' => $banner]);
    }
}
