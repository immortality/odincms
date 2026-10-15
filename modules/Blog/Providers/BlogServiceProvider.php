<?php

namespace Modules\Blog\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use App\Cms\Settings\Settings;
use App\Cms\Support\LinkableRegistry;
use Modules\Blog\Models\Post;
use Modules\Categories\Models\Category;
use Modules\Categories\Support\CategoryTypes;

/**
 * The blog: posts in the admin (/admin/blog), the public blog (/blog), the "blog" category type,
 * posts as navigation link targets, the "Latest posts" widget (module.json) and a settings section.
 */
class BlogServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->make(Settings::class)->section('blog', 'blog::admin.settings.section', [
            'per_page' => [
                'type' => 'number',
                'label' => 'blog::admin.settings.per_page',
                'default' => 10,
                'rules' => ['required', 'integer', 'min:1', 'max:100'],
            ],
        ], module: 'blog', icon: 'fas fa-newspaper', order: 60);
    }

    public function boot(): void
    {
        parent::boot();

        // Categories is a declared dependency ("requires"), so it is loaded whenever Blog is.
        $this->app->make(CategoryTypes::class)->register('blog', 'blog::admin.category_type',
            fn (Category $category) => route('blog.category', $category->slug));

        $this->app->make(LinkableRegistry::class)->register(Post::class, 'blog::admin.linkable');
    }
}
