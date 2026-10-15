<?php

namespace App\Cms\Modules;

use App\Cms\Modules\Contracts\PermissionSynchronizer;
use App\Cms\Modules\Contracts\WidgetInstanceCleaner;
use App\Cms\Modules\Events\ModuleDisabled;
use App\Cms\Modules\Events\ModuleEnabled;
use App\Cms\Modules\Events\ModuleInstalled;
use App\Cms\Modules\Events\ModuleUninstalled;
use App\Cms\Modules\Events\ModuleUpgraded;
use App\Cms\Modules\Exceptions\InvalidManifestException;
use App\Cms\Modules\Exceptions\ModuleException;
use App\Cms\Support\CmsCache;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Discovers the modules in modules/, keeps the `modules` table in sync and runs the
 * module lifecycle: install, enable, disable, uninstall, upgrade (spec §5.3).
 */
class ModuleManager
{
    /** Cache key of the enabled-modules list (spec §5.4: cached forever, cleared on every lifecycle action). */
    public const ENABLED_CACHE_KEY = 'cms.modules.enabled';

    /** Cache key of the enabled modules' manifest files (slug => path). */
    public const ENABLED_MANIFESTS_CACHE_KEY = 'cms.modules.enabled.manifests';

    /** @var array<string, string> manifest file => error, from the last discover() */
    private array $errors = [];

    /**
     * What allowedActions() needs, computed once until the next flush().
     *
     * @var array{manifests: Collection<string, Manifest>, enabled: list<string>, installed: list<string>}|null
     */
    private ?array $actionContext = null;

    /**
     * @param  list<string>  $coreSlugs  slugs from config('cms.core_modules')
     * @param  list<string>  $disableableCoreSlugs  core modules that may still be disabled
     */
    public function __construct(
        private string $modulesPath,
        private array $coreSlugs,
        private array $disableableCoreSlugs,
        private Repository $cache,
        private CmsCache $cmsCache,
        private Migrator $migrator,
        private Container $container,
        private PermissionSynchronizer $permissions,
    ) {}

    public function modulesPath(): string
    {
        return $this->modulesPath;
    }

    /*
    |--------------------------------------------------------------------------
    | Discovery
    |--------------------------------------------------------------------------
    */

    /**
     * Read every modules/{Folder}/module.json. Invalid manifests are skipped and listed in errors().
     *
     * @return Collection<string, Manifest> keyed by slug, sorted by slug
     */
    public function discover(): Collection
    {
        $this->errors = [];
        $manifests = [];

        foreach (glob($this->modulesPath.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.Manifest::FILE) ?: [] as $file) {
            try {
                $manifest = Manifest::fromFile($file);
            } catch (InvalidManifestException $e) {
                $this->errors[$file] = $e->getMessage();

                continue;
            }

            if (isset($manifests[$manifest->slug])) {
                $this->errors[$file] = "Duplicate module slug \"{$manifest->slug}\": already used by {$manifests[$manifest->slug]->path}.";

                continue;
            }

            $manifests[$manifest->slug] = $manifest;
        }

        ksort($manifests);

        return collect($manifests);
    }

    /**
     * Errors from the last discover() or sync(): manifest file => message.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * The manifest of one module, or null when its folder or manifest is missing or invalid.
     */
    public function manifest(string $slug): ?Manifest
    {
        return $this->discover()->get($slug);
    }

    /**
     * The modules folder differs from the `modules` table, so sync() would change something
     * (a new folder, a new version or name, a folder that disappeared or came back). Read-only.
     */
    public function needsSync(): bool
    {
        $manifests = $this->discover();
        $rows = Module::all()->keyBy('slug');

        foreach ($manifests as $slug => $manifest) {
            /** @var Module|null $row */
            $row = $rows->get($slug);
            if ($row === null || $row->is_missing || $row->version !== $manifest->version || $row->name !== $manifest->name) {
                return true;
            }
        }

        return $rows->contains(fn (Module $row) => ! $row->is_missing && ! $manifests->has($row->slug));
    }

    /**
     * The given modules plus everything they require, sorted so that each module comes after the
     * modules it requires (the order to install them in).
     *
     * @param  list<string>  $slugs
     * @return list<string>
     *
     * @throws ModuleException when a required module is not in the modules folder, or on a cycle
     */
    public function dependencyOrder(array $slugs): array
    {
        $manifests = $this->discover();
        $ordered = [];
        $visiting = [];

        $visit = function (string $slug, ?string $requiredBy) use (&$visit, &$ordered, &$visiting, $manifests): void {
            if (in_array($slug, $ordered, true)) {
                return;
            }
            if (isset($visiting[$slug])) {
                throw new ModuleException('Modules require each other in a circle: '.implode(' → ', [...array_keys($visiting), $slug]).'.');
            }

            $manifest = $manifests->get($slug);
            if ($manifest === null) {
                throw $requiredBy === null
                    ? ModuleException::notFound($slug)
                    : ModuleException::missingDependencies($requiredBy, [$slug]);
            }

            $visiting[$slug] = true;
            foreach ($manifest->requires() as $required) {
                $visit($required, $slug);
            }
            unset($visiting[$slug]);

            $ordered[] = $slug;
        };

        foreach ($slugs as $slug) {
            $visit($slug, null);
        }

        return $ordered;
    }

    /**
     * Bring the `modules` table in line with the modules folder:
     *  - new folders are added as "not installed";
     *  - name, version and core flag are refreshed from the manifest;
     *  - rows whose folder is gone (or whose manifest is invalid) are flagged missing, and
     *    unflagged when the folder is back. Their data and install state are kept.
     */
    public function sync(): SyncResult
    {
        $manifests = $this->discover();
        $added = $updated = $restored = $missing = [];

        DB::transaction(function () use ($manifests, &$added, &$updated, &$restored, &$missing) {
            $existing = Module::all()->keyBy('slug');

            foreach ($manifests as $slug => $manifest) {
                $attributes = [
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'is_core' => $this->isCoreManifest($manifest),
                ];

                /** @var Module|null $module */
                $module = $existing->get($slug);

                if ($module === null) {
                    Module::create(['slug' => $slug, ...$attributes]);
                    $added[] = $slug;

                    continue;
                }

                $module->fill($attributes);
                if ($module->isDirty()) {
                    $updated[] = $slug;
                }
                if ($module->is_missing) {
                    $module->is_missing = false;
                    $restored[] = $slug;
                }
                $module->save();
            }

            foreach ($existing as $slug => $module) {
                if (! $manifests->has($slug) && ! $module->is_missing) {
                    $module->update(['is_missing' => true]);
                    $missing[] = $slug;
                }
            }
        });

        $this->flush();

        return new SyncResult($added, $updated, $restored, $missing, $this->errors);
    }

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */

    /**
     * Run the module's migrations and seeder, create its permissions and enable it.
     * Every module in "requires" must already be installed and enabled.
     *
     * @throws ModuleException
     */
    public function install(string $slug): Module
    {
        $module = $this->find($slug);
        $manifest = $this->manifestFor($module);

        if ($module->isInstalled()) {
            throw ModuleException::alreadyInstalled($slug);
        }
        $this->ensureDependenciesEnabled($manifest);

        // Schema changes commit implicitly in MySQL, so migrations run outside the transaction.
        // If the seeder fails, the migrations are rolled back so the module stays "not installed".
        $this->migrate($manifest);

        try {
            $this->seed($manifest);

            DB::transaction(function () use ($module, $manifest) {
                $this->permissions->sync($manifest);

                $module->update([
                    'installed_at' => now(),
                    'installed_version' => $manifest->version,
                    'version' => $manifest->version,
                    'enabled' => true,
                    'is_missing' => false,
                ]);
            });
        } catch (Throwable $e) {
            $this->rollbackMigrations($manifest);

            throw $e;
        }

        $this->flush();
        ModuleInstalled::dispatch($module);

        return $module;
    }

    /**
     * @throws ModuleException
     */
    public function enable(string $slug): Module
    {
        $module = $this->find($slug);
        $manifest = $this->manifestFor($module);

        if (! $module->isInstalled()) {
            throw ModuleException::notInstalled($slug);
        }
        if ($module->enabled) {
            return $module;
        }
        $this->ensureDependenciesEnabled($manifest);

        $module->update(['enabled' => true]);

        $this->flush();
        ModuleEnabled::dispatch($module);

        return $module;
    }

    /**
     * Disable a module: its provider, routes, menu items and widgets stop loading. Data stays.
     *
     * @throws ModuleException
     */
    public function disable(string $slug): Module
    {
        $module = $this->find($slug);

        if (! $module->isInstalled()) {
            throw ModuleException::notInstalled($slug);
        }
        if ($module->is_core && ! in_array($slug, $this->disableableCoreSlugs, true)) {
            throw ModuleException::coreProtected($slug, 'disabled');
        }
        if (! $module->enabled) {
            return $module;
        }

        $dependents = $this->dependentsOf($slug, $this->enabled()->pluck('slug')->all());
        if ($dependents !== []) {
            throw ModuleException::hasDependents($slug, 'disabled', $dependents);
        }

        $module->update(['enabled' => false]);

        $this->flush();
        ModuleDisabled::dispatch($module);

        return $module;
    }

    /**
     * Roll back the module's migrations (its data is deleted), remove its permissions and
     * widget instances, and reset it to "not installed". The folder stays, so it can be
     * installed again.
     *
     * @throws ModuleException
     */
    public function uninstall(string $slug): Module
    {
        $module = $this->find($slug);

        if (! $module->isInstalled()) {
            throw ModuleException::notInstalled($slug);
        }
        if ($module->is_core) {
            throw ModuleException::coreProtected($slug, 'uninstalled');
        }

        $installed = Module::installed()->where('slug', '!=', $slug)->pluck('slug')->all();
        $dependents = $this->dependentsOf($slug, $installed);
        if ($dependents !== []) {
            throw ModuleException::hasDependents($slug, 'uninstalled', $dependents);
        }

        // The migration files are needed to roll back.
        $manifest = $this->manifestFor($module);

        $this->rollbackMigrations($manifest);

        DB::transaction(function () use ($module, $slug) {
            $this->permissions->remove($slug);
            // Resolved now: the Widgets module binds the real cleaner after this manager is created.
            $this->container->make(WidgetInstanceCleaner::class)->remove($slug);

            $module->update([
                'installed_at' => null,
                'installed_version' => null,
                'enabled' => false,
            ]);
        });

        $this->flush();
        ModuleUninstalled::dispatch($module);

        return $module;
    }

    /**
     * Run the new migrations of a module whose manifest version is newer than the installed one.
     *
     * @throws ModuleException
     */
    public function upgrade(string $slug): Module
    {
        $module = $this->find($slug);
        $manifest = $this->manifestFor($module);

        if (! $module->isInstalled()) {
            throw ModuleException::notInstalled($slug);
        }

        // Compare with the manifest on disk, even if sync hasn't run since it changed.
        $module->version = $manifest->version;
        if (! $module->needsUpgrade()) {
            throw ModuleException::noUpgrade($slug, (string) $module->installed_version, $manifest->version);
        }

        $from = (string) $module->installed_version;

        $this->migrate($manifest);

        DB::transaction(function () use ($module, $manifest) {
            $this->permissions->sync($manifest);

            $module->update(['installed_version' => $manifest->version]);
        });

        $this->flush();
        ModuleUpgraded::dispatch($module, $from);

        return $module;
    }

    /**
     * The lifecycle actions that are allowed for $module right now ("install", "enable",
     * "disable", "upgrade", "uninstall"), by the same rules as the actions themselves.
     * Used by the Modules screen to show only the buttons that will work.
     *
     * @return list<string>
     */
    public function allowedActions(Module $module): array
    {
        $context = $this->actionContext ??= [
            'manifests' => $this->discover(),
            'enabled' => $this->enabled()->pluck('slug')->all(),
            'installed' => Module::installed()->pluck('slug')->all(),
        ];

        $manifest = $module->is_missing ? null : $context['manifests']->get($module->slug);
        if ($manifest === null) {
            return []; // folder missing or manifest invalid: nothing can be done until it is back
        }

        $dependenciesEnabled = array_diff($manifest->requires(), $context['enabled']) === [];

        if (! $module->isInstalled()) {
            return $dependenciesEnabled ? ['install'] : [];
        }

        $actions = [];

        if (! $module->enabled && $dependenciesEnabled) {
            $actions[] = 'enable';
        }

        $disableable = ! $module->is_core || in_array($module->slug, $this->disableableCoreSlugs, true);
        if ($module->enabled && $disableable && $this->dependentsOf($module->slug, $context['enabled']) === []) {
            $actions[] = 'disable';
        }

        if ($module->installed_version !== null && version_compare($manifest->version, $module->installed_version, '>')) {
            $actions[] = 'upgrade';
        }

        $others = array_values(array_diff($context['installed'], [$module->slug]));
        if (! $module->is_core && $this->dependentsOf($module->slug, $others) === []) {
            $actions[] = 'uninstall';
        }

        return $actions;
    }

    /*
    |--------------------------------------------------------------------------
    | Enabled modules and caches
    |--------------------------------------------------------------------------
    */

    /**
     * The modules that are booted: installed, enabled and with their folder present, in sort order.
     * Cached forever; returns an empty collection before the `modules` table exists (fresh install).
     *
     * @return EloquentCollection<int, Module>
     */
    public function enabled(): EloquentCollection
    {
        return Module::hydrate($this->enabledRows());
    }

    /**
     * The enabled modules as plain rows (cached). Uses the query builder, not Eloquent:
     * ModuleLoader calls this while the app is booting, before Eloquent has its connection.
     *
     * @return list<array<string, mixed>>
     */
    private function enabledRows(): array
    {
        /** @var list<array<string, mixed>>|null $rows */
        $rows = $this->cache->get(self::ENABLED_CACHE_KEY);

        if ($rows === null) {
            $table = 'modules';

            if (! Schema::hasTable($table)) {
                return [];
            }

            // Same conditions as Module::active()->ordered().
            $rows = DB::table($table)
                ->whereNotNull('installed_at')
                ->where('enabled', true)
                ->where('is_missing', false)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (object $row) => (array) $row)
                ->all();

            $this->cache->forever(self::ENABLED_CACHE_KEY, $rows);
        }

        return $rows;
    }

    public function isEnabled(string $slug): bool
    {
        return $this->enabled()->contains('slug', $slug);
    }

    /**
     * The manifests of the enabled modules, in boot order. Used on every request by ModuleLoader,
     * so the manifest file paths are cached (no folder scan). A manifest that has become
     * unreadable since is skipped and logged instead of breaking the site.
     *
     * @return Collection<string, Manifest> keyed by slug
     */
    public function enabledManifests(): Collection
    {
        /** @var array<string, string>|null $files */
        $files = $this->cache->get(self::ENABLED_MANIFESTS_CACHE_KEY);

        if ($files === null) {
            $slugs = array_column($this->enabledRows(), 'slug');
            $discovered = $this->discover();

            $files = [];
            foreach ($slugs as $slug) {
                if ($manifest = $discovered->get($slug)) {
                    $files[$slug] = $manifest->path(Manifest::FILE);
                }
            }

            $this->cache->forever(self::ENABLED_MANIFESTS_CACHE_KEY, $files);
        }

        $manifests = [];
        foreach ($files as $slug => $file) {
            try {
                $manifests[$slug] = Manifest::fromFile($file);
            } catch (InvalidManifestException $e) {
                Log::warning("OdinCMS: module \"{$slug}\" not loaded. ".$e->getMessage());
            }
        }

        return collect($manifests);
    }

    /**
     * Clear the module, admin menu, widget and navigation caches (spec §12), and the route
     * cache, which would otherwise keep a disabled module's routes.
     */
    public function flush(): void
    {
        $this->cache->forget(self::ENABLED_CACHE_KEY);
        $this->cache->forget(self::ENABLED_MANIFESTS_CACHE_KEY);
        $this->cmsCache->flush();
        $this->actionContext = null;

        if ($this->container instanceof Application && $this->container->routesAreCached()) {
            @unlink($this->container->getCachedRoutesPath());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * @throws ModuleException
     */
    private function find(string $slug): Module
    {
        return Module::where('slug', $slug)->first() ?? throw ModuleException::notFound($slug);
    }

    /**
     * @throws ModuleException
     */
    private function manifestFor(Module $module): Manifest
    {
        $manifest = $module->is_missing ? null : $this->manifest($module->slug);

        return $manifest ?? throw ModuleException::folderMissing($module->slug);
    }

    private function isCoreManifest(Manifest $manifest): bool
    {
        return $manifest->isCore() || in_array($manifest->slug, $this->coreSlugs, true);
    }

    /**
     * @throws ModuleException
     */
    private function ensureDependenciesEnabled(Manifest $manifest): void
    {
        $enabled = $this->enabled()->pluck('slug')->all();
        $missing = array_values(array_diff($manifest->requires(), $enabled));

        if ($missing !== []) {
            throw ModuleException::missingDependencies($manifest->slug, $missing);
        }
    }

    /**
     * Which of the given modules require $slug in their manifest.
     *
     * @param  list<string>  $candidates
     * @return list<string>
     */
    private function dependentsOf(string $slug, array $candidates): array
    {
        return $this->discover()
            ->only($candidates)
            ->filter(fn (Manifest $manifest) => in_array($slug, $manifest->requires(), true))
            ->keys()
            ->values()
            ->all();
    }

    private function migrationsPath(Manifest $manifest): ?string
    {
        $path = $manifest->path('Database'.DIRECTORY_SEPARATOR.'Migrations');

        return is_dir($path) ? $path : null;
    }

    private function migrate(Manifest $manifest): void
    {
        if ($path = $this->migrationsPath($manifest)) {
            $this->migrator->run([$path]);
        }
    }

    /**
     * Roll back exactly the migrations in the module's folder, whatever batch they ran in.
     */
    private function rollbackMigrations(Manifest $manifest): void
    {
        if ($path = $this->migrationsPath($manifest)) {
            $this->migrator->reset([$path]);
        }
    }

    /**
     * Run Modules\{Folder}\Database\Seeders\{Folder}DatabaseSeeder when the module has one.
     */
    private function seed(Manifest $manifest): void
    {
        $class = $manifest->namespace().'\\Database\\Seeders\\'.$manifest->folder().'DatabaseSeeder';

        if (class_exists($class) && is_subclass_of($class, Seeder::class)) {
            /** @var Seeder $seeder */
            $seeder = $this->container->make($class);
            $seeder->setContainer($this->container)->__invoke();
        }
    }
}
