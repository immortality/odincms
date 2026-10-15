<?php

namespace Modules\Pages\Widgets;

use App\Cms\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Modules\Pages\Models\Page;

/**
 * A page as a home page section (e.g. "About us"): the widget title as the small label, the slogan
 * (default: the page title), the intro (default: the start of the page text), the detail (formatted
 * content), the page image and a link to the page. Shows nothing while the page is a draft or deleted.
 */
class PageWidget extends Widget
{
    public static bool $ownHeading = true;

    public static string $key = 'pages.page';

    public static string $name = 'pages::widgets.page.name';

    public static string $description = 'pages::widgets.page.description';

    public static string $icon = 'fas fa-file-alt';

    public function fields(): array
    {
        return [
            'page' => [
                'type' => 'select',
                'label' => 'pages::widgets.page.page',
                'options' => fn () => Page::options(),
                'placeholder' => 'pages::widgets.page.choose',
                'rules' => ['required'],
            ],
            ...$this->headingFields('pages::widgets.page.slogan_help', 'pages::widgets.page.intro_help'),
            'link' => [
                'type' => 'checkbox',
                'label' => 'pages::widgets.page.link',
                'default' => true,
            ],
        ];
    }

    public function render(): View|string
    {
        $page = Page::published()->with('media')->find((int) $this->setting('page'));
        if ($page === null) {
            return '';
        }

        return view($this->themeView('pages', 'page'), [
            'page' => $page,
            'eyebrow' => $this->title(),
            'slogan' => $this->translated('slogan') ?: $page->label(),
            'intro' => $this->translated('intro') ?: $page->summary(300),
            'detail' => $this->translated('detail'), // purified on save
            'image' => $page->imageUrl('image', 'large'),
            'link' => (bool) $this->setting('link'),
        ]);
    }
}
