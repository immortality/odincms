<?php

namespace App\Cms\Modules\Console;

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('cms:module:disable {slug : The module slug, e.g. blog}')]
#[Description('Disable a module (its data is kept)')]
class DisableModuleCommand extends ModuleActionCommand
{
    protected function perform(ModuleManager $modules, string $slug): Module
    {
        return $modules->disable($slug);
    }

    protected function successMessage(Module $module): string
    {
        return "Module \"{$module->slug}\" disabled. Its data is kept.";
    }
}
