<?php

namespace App\Cms\Modules;

use App\Cms\Media\ImageSizes;
use App\Cms\Widgets\WidgetRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Base class for module providers (spec §5.4). For the module in modules/Blog it:
 *  - loads views as "blog::" from Resources/views and translations as "blog::" from Resources/lang;
 *  - loads migrations from Database/Migrations;
 *  - registers Routes/admin.php under /{admin_prefix}/blog, named "admin.blog.", with the
 *    admin middleware from config('cms.admin_middleware') (web, auth, verified, admin);
 *  - registers Routes/web.php with the web middleware;
 *  - registers the policies in $policies and the widget types listed in module.json.
 *
 * A module provider that overrides boot() must call parent::boot().
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Model => policy.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [];

    /**
     * Middleware of Routes/admin.php; null = config('cms.admin_middleware') (web, auth, verified, admin).
     *
     * @var list<string>|null
     */
    protected ?array $adminMiddleware = null;

    /**
     * Middleware of Routes/web.php. The web group already sets the locale (SetLocale).
     *
     * @var list<string>
     */
    protected array $webMiddleware = ['web'];

    protected ?Manifest $manifest = null;

    /**
     * Set by ModuleLoader. Without it, the manifest is read from the module folder.
     */
    public function setManifest(Manifest $manifest): static
    {
        $this->manifest = $manifest;

        return $this;
    }

    public function manifest(): Manifest
    {
        // The provider lives in modules/{Folder}/Providers, so the module folder is two levels up.
        return $this->manifest ??= Manifest::fromFile(
            dirname((string) (new ReflectionClass(static::class))->getFileName(), 2).DIRECTORY_SEPARATOR.Manifest::FILE
        );
    }

    public function boot(): void
    {
        $manifest = $this->manifest();
        $slug = $manifest->slug;

        if (is_dir($views = $manifest->path('Resources/views'))) {
            $this->loadViewsFrom($views, $slug);
        }

        if (is_dir($lang = $manifest->path('Resources/lang'))) {
            $this->loadTranslationsFrom($lang, $slug);
        }

        if (is_dir($migrations = $manifest->path('Database/Migrations'))) {
            $this->loadMigrationsFrom($migrations);
        }

        if (! $this->app->routesAreCached()) {
            $this->registerRoutes($manifest);
        }

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Its cropped image sizes appear in Admin → Settings → Images (module.json "images").
        if ($manifest->images() !== []) {
            $this->app->make(ImageSizes::class)->registerSettings();
        }

        // The widget types the module offers (module.json "widgets").
        $widgets = $this->app->make(WidgetRegistry::class);
        foreach ($manifest->widgets() as $class) {
            if (class_exists($class)) {
                $widgets->register($class);
            } else {
                Log::warning("OdinCMS: module \"{$slug}\" lists the widget {$class}, which does not exist.");
            }
        }
    }

    protected function registerRoutes(Manifest $manifest): void
    {
        if (is_file($admin = $manifest->path('Routes/admin.php'))) {
            Route::middleware($this->adminMiddleware ?? (array) config('cms.admin_middleware'))
                ->prefix(trim((string) config('cms.admin_prefix'), '/').'/'.$manifest->slug)
                ->name('admin.'.$manifest->slug.'.')
                ->group($admin);
        }

        if (is_file($web = $manifest->path('Routes/web.php'))) {
            Route::middleware($this->webMiddleware)->group($web);
        }

        // Route names set with ->name() are indexed once after boot; refresh them so a module
        // loaded later (e.g. by ModuleLoader::load() at runtime) can be found by name too.
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }
}
