<?php

namespace Modules\Services\Widgets;

use App\Cms\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Modules\Services\Models\Service;

/**
 * The newest published services as cards (a home page section) under a heading — the widget title
 * as the small label, the slogan and the intro — with a link to all services.
 */
class ServicesWidget extends Widget
{
    public static string $key = 'services.list';

    public static string $name = 'services::widgets.list.name';

    public static string $description = 'services::widgets.list.description';

    public static string $icon = 'fas fa-concierge-bell';

    public static bool $ownHeading = true;

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            'limit' => [
                'type' => 'number',
                'label' => 'services::widgets.list.limit',
                'default' => 4,
                'rules' => ['required', 'integer', 'min:1', 'max:12'],
            ],
            'link' => [
                'type' => 'checkbox',
                'label' => 'services::widgets.list.link',
                'default' => true,
            ],
        ];
    }

    public function render(): View|string
    {
        $services = Service::published()->with('media')->latest()->latest('id')->take((int) $this->setting('limit'))->get();

        return $services->isEmpty() ? '' : view($this->themeView('services', 'list'), [
            'services' => $services,
            'eyebrow' => $this->title(),
            'slogan' => $this->translated('slogan'),
            'intro' => $this->translated('intro'),
            'detail' => $this->translated('detail'), // purified on save
            'link' => (bool) $this->setting('link'),
        ]);
    }
}
