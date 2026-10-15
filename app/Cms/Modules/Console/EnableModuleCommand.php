<?php

namespace App\Cms\Modules\Console;

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('cms:module:enable {slug : The module slug, e.g. blog}')]
#[Description('Enable an installed module')]
class EnableModuleCommand extends ModuleActionCommand
{
    protected function perform(ModuleManager $modules, string $slug): Module
    {
        return $modules->enable($slug);
    }

    protected function successMessage(Module $module): string
    {
        return "Module \"{$module->slug}\" enabled.";
    }
}
