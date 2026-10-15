<?php

namespace App\Cms\Modules\Console;

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('cms:module:uninstall {slug : The module slug, e.g. blog} {--force : Skip the confirmation}')]
#[Description('Uninstall a module: roll back its migrations (deletes its data) and remove its permissions')]
class UninstallModuleCommand extends ModuleActionCommand
{
    protected function confirmAction(string $slug): bool
    {
        return $this->option('force')
            || $this->confirm("Uninstall \"{$slug}\"? Its database tables and data will be deleted.");
    }

    protected function perform(ModuleManager $modules, string $slug): Module
    {
        return $modules->uninstall($slug);
    }

    protected function successMessage(Module $module): string
    {
        return "Module \"{$module->slug}\" uninstalled.";
    }
}
