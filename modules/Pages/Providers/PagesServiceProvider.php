<?php

namespace Modules\Pages\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use App\Cms\Support\LinkableRegistry;
use Modules\Pages\Models\Page;

/**
 * Static pages: the tree in the admin (/admin/pages), and the fallback route that serves a
 * published page at its path (Routes/web.php). Pages can be linked from menus.
 */
class PagesServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->app->make(LinkableRegistry::class)->register(Page::class, 'pages::admin.linkable');
    }
}
