<?php

namespace App\Cms\Modules\Console;

use App\Cms\Modules\Exceptions\ModuleException;
use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use Illuminate\Console\Command;

/**
 * Base for cms:module:{install|enable|disable|uninstall|upgrade} {slug}.
 */
abstract class ModuleActionCommand extends Command
{
    /**
     * Run the lifecycle action and return the module.
     *
     * @throws ModuleException
     */
    abstract protected function perform(ModuleManager $modules, string $slug): Module;

    /**
     * The message shown on success, e.g. "Module "blog" installed (1.0.0)."
     */
    abstract protected function successMessage(Module $module): string;

    /**
     * Ask before destructive actions; return false to cancel.
     */
    protected function confirmAction(string $slug): bool
    {
        return true;
    }

    public function handle(ModuleManager $modules): int
    {
        $slug = (string) $this->argument('slug');

        if (! $this->confirmAction($slug)) {
            $this->components->warn('Cancelled.');

            return self::FAILURE;
        }

        try {
            $module = $this->perform($modules, $slug);
        } catch (ModuleException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($this->successMessage($module));

        return self::SUCCESS;
    }
}
