<?php

namespace App\Cms\Modules\Console;

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('cms:module:upgrade {slug : The module slug, e.g. blog}')]
#[Description('Upgrade a module to the version in its module.json (runs its new migrations)')]
class UpgradeModuleCommand extends ModuleActionCommand
{
    protected function perform(ModuleManager $modules, string $slug): Module
    {
        return $modules->upgrade($slug);
    }

    protected function successMessage(Module $module): string
    {
        return "Module \"{$module->slug}\" upgraded to {$module->installed_version}.";
    }
}
