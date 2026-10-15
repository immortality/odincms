<?php

namespace App\Cms\Modules;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Registers the providers of the enabled modules (spec §5.4, boot sequence).
 * Disabled modules are never loaded, so their routes don't exist.
 */
class ModuleLoader
{
    /** @var array<string, true> slugs already loaded */
    private array $loaded = [];

    public function __construct(
        private Application $app,
        private ModuleManager $modules,
    ) {}

    /**
     * Register every enabled module that isn't loaded yet. Safe to call again.
     */
    public function load(): void
    {
        try {
            $manifests = $this->modules->enabledManifests();
        } catch (Throwable $e) {
            // No database yet (fresh install, composer install, ...): run without modules.
            Log::warning('OdinCMS: modules not loaded: '.$e->getMessage());

            return;
        }

        foreach ($manifests as $slug => $manifest) {
            if (isset($this->loaded[$slug])) {
                continue;
            }

            $provider = new ($manifest->provider)($this->app);

            if ($provider instanceof ModuleServiceProvider) {
                $provider->setManifest($manifest);
            }

            $this->app->register($provider);
            $this->loaded[$slug] = true;
        }
    }

    /**
     * @return list<string>
     */
    public function loaded(): array
    {
        return array_keys($this->loaded);
    }
}
