<?php

namespace Modules\Services\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use App\Cms\Support\LinkableRegistry;
use Modules\Services\Models\Service;

/**
 * Services: the admin (/admin/services), the public pages (/services) and services as menu link
 * targets. Views, translations, migrations and routes are loaded by ModuleServiceProvider.
 */
class ServicesServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->app->make(LinkableRegistry::class)->register(Service::class, 'services::admin.linkable');
    }
}
