<?php

namespace Modules\Settings\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use App\Cms\Settings\Settings;

/**
 * The Settings screen and the core settings sections. Other modules add their own sections with
 * Settings::section() in their provider. Applying the core values to the config is done by
 * App\Cms\Settings\ApplySettings at boot.
 */
class SettingsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $settings = $this->app->make(Settings::class);
        $localeOptions = fn () => collect((array) config('cms.available_locales'))
            ->mapWithKeys(fn (string $locale) => [$locale => config("cms.locale_names.{$locale}", strtoupper($locale))])
            ->all();

        $settings->section('site', 'settings::admin.sections.site', [
            'name' => [
                'type' => 'translatable',
                'label' => 'settings::admin.fields.site_name',
                'default' => fn () => [(string) config('cms.default_locale') => (string) config('app.name')],
                'rules' => ['required'],
            ],
            'tagline' => [
                'type' => 'translatable',
                'label' => 'settings::admin.fields.tagline',
                'help' => 'settings::admin.help.tagline',
            ],
        ], icon: 'fas fa-globe', order: 10);

        $settings->section('locales', 'settings::admin.sections.locales', [
            'active' => [
                'type' => 'multiselect',
                'label' => 'settings::admin.fields.active_locales',
                'help' => 'settings::admin.help.active_locales',
                'options' => $localeOptions,
                'default' => fn () => (array) config('cms.available_locales'),
                'rules' => ['required', 'min:1'],
            ],
            'default' => [
                'type' => 'select',
                'label' => 'settings::admin.fields.default_locale',
                'help' => 'settings::admin.help.default_locale',
                'options' => $localeOptions,
                'default' => fn () => (string) config('cms.default_locale'),
                'rules' => ['required'],
            ],
        ], icon: 'fas fa-globe', order: 20);

        $settings->section('mail', 'settings::admin.sections.mail', [
            'from_address' => [
                'type' => 'email',
                'label' => 'settings::admin.fields.from_address',
                'default' => fn () => config('mail.from.address'),
            ],
            'from_name' => [
                'type' => 'text',
                'label' => 'settings::admin.fields.from_name',
                'help' => 'settings::admin.help.from_name',
            ],
        ], icon: 'far fa-envelope', order: 30);

        $settings->section('users', 'settings::admin.sections.users', [
            'registration' => [
                'type' => 'checkbox',
                'label' => 'settings::admin.fields.registration',
                'help' => 'settings::admin.help.registration',
                'default' => false,
            ],
        ], icon: 'fas fa-users-cog', order: 40);

        $settings->section('maintenance', 'settings::admin.sections.maintenance', [
            'enabled' => [
                'type' => 'checkbox',
                'label' => 'settings::admin.fields.maintenance',
                'help' => 'settings::admin.help.maintenance',
                'default' => false,
            ],
            'message' => [
                'type' => 'translatable',
                'label' => 'settings::admin.fields.maintenance_message',
            ],
        ], icon: 'fas fa-exclamation-triangle', order: 90);
    }
}
