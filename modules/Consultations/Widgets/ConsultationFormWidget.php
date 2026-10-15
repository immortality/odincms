<?php

namespace Modules\Consultations\Widgets;

use App\Cms\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Modules\Services\Models\Service;

/**
 * "Request a free consultation": a blue box with a figure ("40+" happy clients), a heading (the widget
 * title as the eyebrow, the slogan, the intro) and the request form (name, e-mail, a published service,
 * message). Never cached: the form carries the visitor's CSRF token, errors and input.
 */
class ConsultationFormWidget extends Widget
{
    public static string $key = 'consultations.form';

    public static string $name = 'consultations::widgets.form.name';

    public static string $description = 'consultations::widgets.form.description';

    public static string $icon = 'fas fa-paper-plane';

    public static bool $ownHeading = true;

    public static bool $cacheable = false;

    public function fields(): array
    {
        $heading = $this->headingFields();

        return [
            'slogan' => [...$heading['slogan'], 'required' => true, 'max' => 120],
            'intro' => [...$heading['intro'], 'max' => 400],
            'stat_label' => [
                'type' => 'translatable',
                'label' => 'consultations::widgets.form.stat_label',
                'help' => 'consultations::widgets.form.stat_label_help',
                'max' => 80,
            ],
            'form_title' => [
                'type' => 'translatable',
                'label' => 'consultations::widgets.form.form_title',
                'help' => 'consultations::widgets.form.default_help',
                'max' => 80,
            ],
            'button' => [
                'type' => 'translatable',
                'label' => 'consultations::widgets.form.button',
                'help' => 'consultations::widgets.form.default_help',
                'max' => 40,
            ],
            'stat_value' => [
                'type' => 'text',
                'label' => 'consultations::widgets.form.stat_value',
                'help' => 'consultations::widgets.form.stat_value_help',
                'rules' => ['max:12'],
            ],
        ];
    }

    public function render(): View
    {
        $statValue = trim((string) $this->setting('stat_value'));

        return view($this->themeView('consultations', 'form'), [
            'eyebrow' => $this->title(),
            'slogan' => $this->translated('slogan'),
            'intro' => $this->translated('intro'),
            'stat' => $statValue === '' ? null : ['value' => $statValue, 'label' => $this->translated('stat_label')],
            'formTitle' => $this->translated('form_title') ?: __('consultations::front.form_title'),
            'button' => $this->translated('button') ?: __('consultations::front.submit'),
            'services' => Service::published()->latest()->latest('id')->get()
                ->mapWithKeys(fn (Service $service) => [$service->id => (string) $service->title])->all(),
        ]);
    }
}
