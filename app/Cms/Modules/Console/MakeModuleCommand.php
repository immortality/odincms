<?php

namespace App\Cms\Modules\Console;

use App\Cms\Modules\ModuleManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

#[Signature('cms:make:module {name : The module name in StudlyCase, e.g. Tours or TourBookings} {--force : Overwrite an existing module folder}')]
#[Description('Create a new module from stubs/module: manifest, provider, routes, CRUD controller, model, migration, policy, views, translations and a test')]
class MakeModuleCommand extends Command
{
    public function handle(Filesystem $files, ModuleManager $modules): int
    {
        $name = (string) $this->argument('name');

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->components->error("The module name must be StudlyCase (e.g. \"Tours\" or \"TourBookings\"), got \"{$name}\".");

            return self::FAILURE;
        }

        $target = $modules->modulesPath().DIRECTORY_SEPARATOR.$name;
        if ($files->isDirectory($target) && ! $this->option('force')) {
            $this->components->error("The folder {$target} already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        $stubs = base_path('stubs'.DIRECTORY_SEPARATOR.'module');
        $replacements = $this->replacements($name);
        $created = [];

        foreach ($files->allFiles($stubs) as $stub) {
            $relative = strtr(Str::beforeLast($stub->getRelativePathname(), '.stub'), $replacements);

            // Migrations get a timestamp, like make:migration.
            if (str_starts_with(str_replace('\\', '/', $relative), 'Database/Migrations/')) {
                $relative = dirname($relative).DIRECTORY_SEPARATOR.date('Y_m_d_His').'_'.basename($relative);
            }

            $path = $target.DIRECTORY_SEPARATOR.$relative;
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, strtr($stub->getContents(), $replacements));
            $created[] = $relative;
        }

        sort($created);
        $this->components->info("Module \"{$name}\" created in {$target}.");
        foreach ($created as $file) {
            $this->components->twoColumnDetail($file);
        }

        $slug = $replacements['{{slug}}'];
        $this->newLine();
        $this->line('  Next steps:');
        $this->line('    php artisan cms:modules:sync');
        $this->line("    php artisan cms:module:install {$slug}");
        $this->line("  The admin screen is then at /{$this->adminPrefix()}/{$slug}.");

        return self::SUCCESS;
    }

    /**
     * Placeholders used in the stubs (file contents and paths).
     *
     * @return array<string, string>
     */
    private function replacements(string $name): array
    {
        $model = Str::singular($name);

        return [
            '{{Name}}' => $name,                                   // TourBookings
            '{{Title}}' => Str::headline($name),                   // Tour Bookings
            '{{slug}}' => Str::kebab($name),                       // tour-bookings
            '{{namespace}}' => 'Modules\\'.$name,                  // Modules\TourBookings
            '{{Model}}' => $model,                                 // TourBooking
            '{{table}}' => Str::snake(Str::pluralStudly($model)),  // tour_bookings
        ];
    }

    private function adminPrefix(): string
    {
        return trim((string) config('cms.admin_prefix'), '/');
    }
}
