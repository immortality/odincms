<?php

namespace Modules\Categories\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use App\Cms\Support\LinkableRegistry;
use Modules\Categories\Models\Category;
use Modules\Categories\Support\CategoryTypes;

/**
 * Nested categories for other modules. A module registers its category type with
 * CategoryTypes::register() from its provider's boot(); see CategoryTypes.
 */
class CategoriesServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CategoryTypes::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(LinkableRegistry::class)->register(Category::class, 'categories::admin.linkable');
    }
}
