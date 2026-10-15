<?php

namespace App\Cms\Settings;

use Illuminate\Contracts\Config\Repository as Config;
use Laravel\Fortify\Features;
use Spatie\Translatable\Translatable;

/**
 * Applies the core settings to the configuration while the app boots (before Fortify registers
 * its routes), so the rest of the code keeps reading config():
 *
 *   site.name                 → app.name
 *   locales.active / .default → cms.locales / cms.default_locale (and app.locale, the content fallback)
 *   mail.from_address / _name → mail.from
 *   users.registration        → Fortify's registration feature
 */
class ApplySettings
{
    public function __construct(private Settings $settings, private Config $config) {}

    public function apply(): void
    {
        $this->locales();

        $name = $this->settings->translated('site.name', (string) $this->config->get('cms.default_locale'));
        if ($name !== '') {
            $this->config->set('app.name', $name);
        }

        if (filled($address = $this->settings->get('mail.from_address'))) {
            $this->config->set('mail.from.address', $address);
        }
        if (filled($fromName = $this->settings->get('mail.from_name'))) {
            $this->config->set('mail.from.name', $fromName);
        }

        if ($this->settings->get('users.registration') === true) {
            $features = (array) $this->config->get('fortify.features', []);
            if (! in_array(Features::registration(), $features, true)) {
                $this->config->set('fortify.features', [Features::registration(), ...$features]);
            }
        }
    }

    private function locales(): void
    {
        $available = (array) $this->config->get('cms.available_locales');

        $active = array_values(array_intersect($available, (array) $this->settings->get('locales.active', [])));
        if ($active !== []) {
            $this->config->set('cms.locales', $active);
        }

        $locales = (array) $this->config->get('cms.locales');
        $default = $this->settings->get('locales.default');
        if (! is_string($default) || ! in_array($default, $locales, true)) {
            $default = in_array($this->config->get('cms.default_locale'), $locales, true)
                ? (string) $this->config->get('cms.default_locale')
                : (string) ($locales[0] ?? 'en');
        }

        $this->config->set('cms.default_locale', $default);
        $this->config->set('app.locale', $default);

        // Content not translated into the visitor's locale shows in the default one (or any one it has).
        app(Translatable::class)->fallback(fallbackLocale: $default, fallbackAny: true);
    }
}
