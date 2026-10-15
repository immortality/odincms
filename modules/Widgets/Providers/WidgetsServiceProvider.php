<?php

namespace Modules\Widgets\Providers;

use App\Cms\Modules\Contracts\WidgetInstanceCleaner;
use App\Cms\Modules\ModuleServiceProvider;
use Modules\Widgets\Support\EloquentWidgetInstanceCleaner;
use Modules\Widgets\Support\WidgetRenderer;

/**
 * Widget areas and instances (spec §9). Themes render an area with the widgetArea Blade directive
 * (registered by the CMS kernel), which uses the "cms.widgets" binding.
 */
class WidgetsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WidgetRenderer::class);
        $this->app->singleton('cms.widgets', fn ($app) => $app->make(WidgetRenderer::class));
        $this->app->bind(WidgetInstanceCleaner::class, EloquentWidgetInstanceCleaner::class);
    }
}
