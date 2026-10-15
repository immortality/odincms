<?php

namespace App\Providers;

use App\Cms\Admin\AdminMenu;
use App\Cms\Console\InstallCommand;
use App\Cms\Media\ImageSizes;
use App\Cms\Modules\Console\DisableModuleCommand;
use App\Cms\Modules\Console\EnableModuleCommand;
use App\Cms\Modules\Console\InstallModuleCommand;
use App\Cms\Modules\Console\MakeModuleCommand;
use App\Cms\Modules\Console\SyncModulesCommand;
use App\Cms\Modules\Console\UninstallModuleCommand;
use App\Cms\Modules\Console\UpgradeModuleCommand;
use App\Cms\Modules\Contracts\PermissionSynchronizer;
use App\Cms\Modules\Contracts\WidgetInstanceCleaner;
use App\Cms\Modules\ModuleLoader;
use App\Cms\Modules\ModuleManager;
use App\Cms\Modules\Support\NullWidgetInstanceCleaner;
use App\Cms\Modules\Support\SpatiePermissionSynchronizer;
use App\Cms\Settings\ApplySettings;
use App\Cms\Settings\Events\SettingsSaved;
use App\Cms\Settings\Settings;
use App\Cms\Support\CmsCache;
use App\Cms\Support\LinkableRegistry;
use App\Cms\Support\LocalizedUrls;
use App\Cms\Widgets\HtmlWidget;
use App\Cms\Widgets\WidgetRegistry;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class CmsServiceProvider extends ServiceProvider
{
    /**
     * Register the CMS kernel services and the enabled modules' providers.
     */
    public function register(): void
    {
        $this->app->singleton(CmsCache::class, fn ($app) => new CmsCache($app->make(Repository::class)));

        $this->app->bind(PermissionSynchronizer::class, SpatiePermissionSynchronizer::class);
        // Until the Widgets module is loaded (it binds the real cleaner), there are no widget instances to clean.
        $this->app->bind(WidgetInstanceCleaner::class, NullWidgetInstanceCleaner::class);

        $this->app->singleton(ModuleManager::class, fn ($app) => new ModuleManager(
            modulesPath: (string) config('cms.modules_path'),
            coreSlugs: array_values(config('cms.core_modules', [])),
            disableableCoreSlugs: array_values(config('cms.disableable_core_modules', [])),
            cache: $app->make(Repository::class),
            cmsCache: $app->make(CmsCache::class),
            migrator: $app->make('migrator'),
            container: $app,
            permissions: $app->make(PermissionSynchronizer::class),
        ));

        $this->app->singleton(ModuleLoader::class);
        $this->app->singleton(Settings::class);
        $this->app->singleton(LinkableRegistry::class);
        $this->app->singleton(ImageSizes::class);
        $this->app->singleton(WidgetRegistry::class, function () {
            $registry = new WidgetRegistry;
            $registry->register(HtmlWidget::class); // core widgets; module widgets come from their manifests

            return $registry;
        });

        // Boot sequence (spec §5.4). This runs in a "booting" callback because deferred services
        // (cache, migrator) can't be resolved while providers are still registering:
        //  1. apply the saved settings to the config (before Fortify registers its routes);
        //  2. register the providers of the enabled modules; they are booted with the others.
        $this->app->booting(function ($app) {
            try {
                $app->make(ApplySettings::class)->apply();
            } catch (Throwable $e) {
                // No database yet (fresh install, composer install, ...): keep the config files' values.
                Log::warning('OdinCMS: settings not applied: '.$e->getMessage());
            }

            $app->make(ModuleLoader::class)->load();
        });
    }

    /**
     * Bootstrap the CMS kernel (Blade directives, view composers, ...).
     */
    public function boot(): void
    {
        // Super admins pass every permission check (spec §7). null = let the normal checks decide.
        Gate::before(fn ($user) => $user instanceof User && $user->hasRole((string) config('cms.super_admin_role')) ? true : null);

        // @navigation('header') / @navigation('footer', 'view.name'): rendered by the Navigation module
        // when it is enabled (binding "cms.navigation"), nothing otherwise, so theme views always compile.
        Blade::directive('navigation', fn (string $expression) => "<?php echo app()->bound('cms.navigation') ? app('cms.navigation')->render({$expression}) : ''; ?>");

        // @widgetArea('sidebar') / @widgetArea('sidebar', 'view.name'): rendered by the Widgets module ("cms.widgets").
        Blade::directive('widgetArea', fn (string $expression) => "<?php echo app()->bound('cms.widgets') ? app('cms.widgets')->render({$expression}) : ''; ?>");

        // A new image size (Settings → Images) re-creates the existing images of that kind.
        Event::listen(SettingsSaved::class, function (SettingsSaved $event) {
            $sizes = $this->app->make(ImageSizes::class);
            foreach (array_keys($event->changed(ImageSizes::SETTINGS_SECTION.'.')) as $setting) {
                $name = substr($setting, strlen(ImageSizes::SETTINGS_SECTION) + 1);
                foreach (array_keys($sizes->all()) as $key) {
                    if (ImageSizes::settingName($key) === $name) {
                        $sizes->regenerate($key);
                    }
                }
            }
        });

        // Frontend URLs carry the current locale's prefix (/ru/blog/…); see LocalizedUrls and LocalePrefix.
        URL::formatPathUsing(fn (string $path) => LocalizedUrls::formatPath($path));
        // Pagination links keep the prefix (the request path has it removed by LocalePrefix).
        Paginator::currentPathResolver(fn () => url()->current());

        // Admin layouts (OdinAdmin): <x-admin-layout::app> and <x-admin-layout::auth>
        Blade::anonymousComponentPath(resource_path('views/admin/layouts'), 'admin-layout');

        // The active theme's own components: <x-theme::contact-link> = themes/{theme}/components/contact-link.blade.php
        Blade::anonymousComponentPath(resource_path('views/themes/'.config('cms.theme').'/components'), 'theme');

        // Theme views get the site name in the visitor's language (Admin → Settings → Site).
        View::composer('themes.*', function ($view) {
            $view->with('siteName', $this->app->make(Settings::class)->translated('site.name', null, (string) config('app.name')));
        });

        // The admin sidebar menu, built from the enabled modules' manifests.
        View::composer('admin.partials.sidebar', function ($view) {
            $view->with('adminMenu', $this->app->make(AdminMenu::class)->build(auth()->user()));
        });

        // Tests: every module's tables exist in the test database, so a module's own tests only
        // need to mark it installed (TestCase::useModule()) instead of running real migrations.
        if ($this->app->runningUnitTests()) {
            foreach ($this->app->make(ModuleManager::class)->discover() as $manifest) {
                if (is_dir($migrations = $manifest->path('Database/Migrations'))) {
                    $this->loadMigrationsFrom($migrations);
                }
            }
        }

        // Static analysis (Larastan) checks that view names exist. Module views are only registered
        // when the module is enabled, so register every module's view and translation namespace here.
        if (defined('__PHPSTAN_RUNNING__')) {
            foreach ($this->app->make(ModuleManager::class)->discover() as $manifest) {
                $this->loadViewsFrom($manifest->path('Resources/views'), $manifest->slug);
                $this->loadTranslationsFrom($manifest->path('Resources/lang'), $manifest->slug);
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModuleCommand::class,
                InstallCommand::class,
                SyncModulesCommand::class,
                InstallModuleCommand::class,
                EnableModuleCommand::class,
                DisableModuleCommand::class,
                UninstallModuleCommand::class,
                UpgradeModuleCommand::class,
            ]);
        }
    }
}
