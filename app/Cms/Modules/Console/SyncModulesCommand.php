<?php

namespace App\Cms\Modules\Console;

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cms:modules:sync')]
#[Description('Scan the modules folder and sync the modules table')]
class SyncModulesCommand extends Command
{
    public function handle(ModuleManager $modules): int
    {
        $result = $modules->sync();

        foreach ([
            'Added' => $result->added,
            'Updated' => $result->updated,
            'Restored' => $result->restored,
            'Missing' => $result->missing,
        ] as $label => $slugs) {
            if ($slugs !== []) {
                $this->components->twoColumnDetail($label, implode(', ', $slugs));
            }
        }

        if (! $result->hasChanges()) {
            $this->components->info('The modules table is up to date.');
        }

        $this->table(
            ['Slug', 'Name', 'Version', 'Installed', 'Core', 'Status'],
            Module::ordered()->get()->map(fn (Module $module) => [
                $module->slug,
                $module->name,
                $module->version,
                $module->installed_version ?? '-',
                $module->is_core ? 'yes' : '',
                match (true) {
                    $module->is_missing => 'missing',
                    ! $module->isInstalled() => 'not installed',
                    $module->enabled => 'enabled',
                    default => 'disabled',
                },
            ])->all(),
        );

        foreach ($result->invalid as $error) {
            $this->components->error($error);
        }

        return $result->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
