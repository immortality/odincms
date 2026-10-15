<?php

namespace Modules\Navigation\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use Modules\Navigation\Support\NavigationRenderer;

/**
 * Menus (spec §10). Themes render them with the navigation directive, which the CMS kernel
 * registers: navigation('header') uses the default Bootstrap view, and
 * navigation('footer', 'themes.default.partials.footer-nav') a view of your own ($items, $slug).
 */
class NavigationServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NavigationRenderer::class);
        // The @navigation directive (registered by the CMS kernel) renders through this binding.
        $this->app->singleton('cms.navigation', fn ($app) => $app->make(NavigationRenderer::class));
    }
}
