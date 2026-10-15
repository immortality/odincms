<?php

namespace App\Cms\Modules\Console;

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('cms:module:install {slug : The module slug, e.g. blog}')]
#[Description('Install a module: run its migrations and seeder, create its permissions and enable it')]
class InstallModuleCommand extends ModuleActionCommand
{
    protected function perform(ModuleManager $modules, string $slug): Module
    {
        return $modules->install($slug);
    }

    protected function successMessage(Module $module): string
    {
        return "Module \"{$module->slug}\" installed ({$module->installed_version}) and enabled.";
    }
}
