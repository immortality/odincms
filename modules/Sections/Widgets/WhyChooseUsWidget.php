<?php

namespace Modules\Sections\Widgets;

use App\Cms\Widgets\Widget;
use Closure;
use Illuminate\Contracts\View\View;
use Modules\Sections\Support\SectionContent;

/**
 * "Why choose us": a decorative illustration beside a heading (the widget title as the eyebrow, the
 * slogan, the intro), two feature cards, a progress bar and an optional button. Plain text only.
 */
class WhyChooseUsWidget extends Widget
{
    /** Bootstrap Icons the cards can use (labels: sections::widgets.icons.*). */
    public const ICONS = [
        'code-slash', 'speedometer2', 'shield-check', 'phone', 'palette', 'cart3',
        'search', 'graph-up-arrow', 'rocket-takeoff', 'headset', 'gear', 'envelope-paper',
    ];

    public static string $key = 'sections.why-choose-us';

    public static string $name = 'sections::widgets.why_choose_us.name';

    public static string $description = 'sections::widgets.why_choose_us.description';

    public static string $icon = 'fas fa-award';

    public static bool $ownHeading = true;

    public function fields(): array
    {
        $heading = $this->headingFields();
        $icons = fn () => collect(self::ICONS)->mapWithKeys(fn (string $icon) => [$icon => __("sections::widgets.icons.{$icon}")])->all();
        $text = fn (string $label, int $max, bool $textarea = false, ?string $help = null) => array_filter([
            'type' => 'translatable',
            'input' => $textarea ? 'textarea' : 'input',
            'label' => $label,
            'max' => $max,
            'help' => $help,
            'rows' => $textarea ? 3 : null,
        ], fn ($value) => $value !== null);

        return [
            'slogan' => [...$heading['slogan'], 'required' => true, 'max' => 120],
            'intro' => [...$heading['intro'], 'max' => 400],
            'badge' => $text('sections::widgets.why_choose_us.badge', 40, help: 'sections::widgets.why_choose_us.badge_help'),
            'card_1_title' => $text('sections::widgets.why_choose_us.card_1_title', 60, help: 'sections::widgets.why_choose_us.card_help'),
            'card_1_text' => $text('sections::widgets.why_choose_us.card_1_text', 200, true),
            'card_2_title' => $text('sections::widgets.why_choose_us.card_2_title', 60, help: 'sections::widgets.why_choose_us.card_help'),
            'card_2_text' => $text('sections::widgets.why_choose_us.card_2_text', 200, true),
            'bar_label' => $text('sections::widgets.why_choose_us.bar_label', 60, help: 'sections::widgets.why_choose_us.bar_label_help'),
            'link_label' => $text('sections::widgets.why_choose_us.link_label', 60, help: 'sections::widgets.why_choose_us.link_label_help'),
            'card_1_icon' => ['type' => 'select', 'label' => 'sections::widgets.why_choose_us.card_1_icon', 'options' => $icons, 'default' => 'code-slash', 'rules' => ['required']],
            'card_2_icon' => ['type' => 'select', 'label' => 'sections::widgets.why_choose_us.card_2_icon', 'options' => $icons, 'default' => 'speedometer2', 'rules' => ['required']],
            'bar_value' => [
                'type' => 'number',
                'label' => 'sections::widgets.why_choose_us.bar_value',
                'default' => 97,
                'rules' => ['required', 'integer', 'min:0', 'max:100'],
            ],
            'link_url' => [
                'type' => 'text',
                'label' => 'sections::widgets.why_choose_us.link_url',
                'help' => 'sections::widgets.why_choose_us.link_url_help',
                'rules' => ['nullable', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && ! SectionContent::isSafeUrl((string) $value)) {
                        $fail(__('sections::widgets.why_choose_us.link_url_invalid'));
                    }
                }],
            ],
        ];
    }

    public function render(): View|string
    {
        $cards = [];
        foreach ([1, 2] as $n) {
            $icon = (string) $this->setting("card_{$n}_icon");
            if (($title = $this->translated("card_{$n}_title")) !== '') {
                $cards[] = ['icon' => in_array($icon, self::ICONS, true) ? $icon : self::ICONS[0], 'title' => $title, 'text' => $this->translated("card_{$n}_text")];
            }
        }

        $barLabel = $this->translated('bar_label');
        $linkLabel = $this->translated('link_label');
        $href = SectionContent::href((string) $this->setting('link_url'));

        return view($this->themeView('sections', 'why-choose-us'), [
            'eyebrow' => $this->title(),
            'slogan' => $this->translated('slogan'),
            'intro' => $this->translated('intro'),
            'badge' => $this->translated('badge'),
            'cards' => $cards,
            'bar' => $barLabel === '' ? null : ['label' => $barLabel, 'value' => max(0, min(100, (int) $this->setting('bar_value')))],
            'link' => $linkLabel === '' || $href === null ? null : ['label' => $linkLabel, 'href' => $href],
        ]);
    }
}
