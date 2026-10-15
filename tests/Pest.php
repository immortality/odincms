<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Fixtures\Modules\FakeModuleServiceProvider;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests and every module's Tests/ folder run against the Laravel
| application and reset the odincms_test database (see phpunit.xml).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../modules/*/Tests');

/*
| Integration tests run real schema changes (module migrations). MySQL commits
| DDL implicitly, which breaks RefreshDatabase's transaction, so these tests
| rebuild the schema for every test instead.
*/

pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Integration');

/*
|--------------------------------------------------------------------------
| Module helpers
|--------------------------------------------------------------------------
*/

/**
 * Write {$modulesPath}/{$folder}/module.json (as JSON, or raw text) and return the file path.
 *
 * @param  array<string, mixed>|string  $manifest
 */
function writeManifest(string $modulesPath, string $folder, array|string $manifest): string
{
    // Same separators as ModuleManager::discover() (glob), so paths can be compared.
    $folderPath = $modulesPath.DIRECTORY_SEPARATOR.$folder;
    File::ensureDirectoryExists($folderPath);
    $file = $folderPath.DIRECTORY_SEPARATOR.'module.json';
    File::put($file, is_string($manifest) ? $manifest : json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $file;
}

/**
 * A complete, valid manifest for the Blog module.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validManifest(array $overrides = []): array
{
    return array_merge([
        'name' => 'Blog',
        'slug' => 'blog',
        'version' => '1.0.0',
        'description' => ['en' => 'Blog posts', 'ru' => 'Блог'],
        'core' => false,
        'provider' => FakeModuleServiceProvider::class,
        'requires' => ['categories'],
        'admin_menu' => [
            'group' => 'content',
            'icon' => 'ni-article',
            'order' => 20,
            'items' => [
                ['label' => 'blog::admin.posts', 'route' => 'admin.blog.posts.index', 'permission' => 'blog.view'],
            ],
        ],
        'permissions' => ['blog.view', 'blog.create', 'blog.publish'],
        'widgets' => ['Modules\\Blog\\Widgets\\LatestPostsWidget'],
    ], $overrides);
}

/**
 * Create a minimal valid module folder {$modulesPath}/{$folder} (slug = kebab-case folder).
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeModule(string $modulesPath, string $folder, array $overrides = []): string
{
    return writeManifest($modulesPath, $folder, array_merge([
        'name' => $folder,
        'slug' => Str::kebab($folder),
        'version' => '1.0.0',
        'provider' => FakeModuleServiceProvider::class,
    ], $overrides));
}

/**
 * Add a migration to a fake module that creates $table (with id, name, timestamps).
 */
function fakeModuleMigration(string $modulesPath, string $folder, string $name, string $table): string
{
    $dir = $modulesPath.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.'Database'.DIRECTORY_SEPARATOR.'Migrations';
    File::ensureDirectoryExists($dir);

    $file = $dir.DIRECTORY_SEPARATOR.$name.'.php';
    File::put($file, <<<PHP
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('{$table}', function (Blueprint \$table) {
                    \$table->id();
                    \$table->string('name')->nullable();
                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('{$table}');
            }
        };
        PHP);

    return $file;
}

/**
 * Add Modules\{Folder}\Database\Seeders\{Folder}DatabaseSeeder to a fake module and load it
 * (the temporary folder is not autoloaded). The seeder inserts one row into $table, or throws when $fail.
 * Use a folder name that no other test seeds, since a class can only be declared once per run.
 */
function fakeModuleSeeder(string $modulesPath, string $folder, string $table, bool $fail = false): void
{
    $dir = $modulesPath.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.'Database'.DIRECTORY_SEPARATOR.'Seeders';
    File::ensureDirectoryExists($dir);

    $body = $fail
        ? "throw new \RuntimeException('Seeder failed');"
        : "\Illuminate\Support\Facades\DB::table('{$table}')->insert(['name' => 'seeded']);";

    // Built separately: in a heredoc, "\{" keeps the brace and breaks the namespace.
    $namespace = 'Modules\\'.$folder.'\\Database\\Seeders';

    $file = $dir.DIRECTORY_SEPARATOR.$folder.'DatabaseSeeder.php';
    File::put($file, <<<PHP
        <?php

        namespace {$namespace};

        use Illuminate\Database\Seeder;

        class {$folder}DatabaseSeeder extends Seeder
        {
            public function run(): void
            {
                {$body}
            }
        }
        PHP);

    require_once $file;
}

/**
 * Create a complete module that boots through ModuleServiceProvider: a provider class,
 * Routes/web.php (GET /{slug}-hello), Routes/admin.php (GET /admin/{slug}, named admin.{slug}.index),
 * a view ({slug}::hello) and a translation ({slug}::messages.hello). The provider file is loaded
 * here because the temporary folder isn't autoloaded; use a folder name no other test uses.
 *
 * @param  array<string, mixed>  $manifest
 */
function bootableModule(string $modulesPath, string $folder, array $manifest = []): string
{
    $slug = Str::kebab($folder);
    $root = $modulesPath.DIRECTORY_SEPARATOR.$folder;
    $namespace = 'Modules\\'.$folder.'\\Providers';
    $provider = $namespace.'\\'.$folder.'ServiceProvider';

    $files = [
        "Providers/{$folder}ServiceProvider.php" => <<<PHP
            <?php

            namespace {$namespace};

            use App\Cms\Modules\ModuleServiceProvider;

            class {$folder}ServiceProvider extends ModuleServiceProvider
            {
            }
            PHP,
        'Routes/web.php' => <<<PHP
            <?php

            use Illuminate\Support\Facades\Route;

            Route::get('/{$slug}-hello', fn () => view('{$slug}::hello'))->name('{$slug}.hello');
            PHP,
        'Routes/admin.php' => <<<PHP
            <?php

            use Illuminate\Support\Facades\Route;

            Route::get('/', fn () => 'admin of {$slug}')->name('index');
            PHP,
        'Resources/views/hello.blade.php' => "Hello from {{ __('{$slug}::messages.hello') }}",
        'Resources/lang/en/messages.php' => "<?php\n\nreturn ['hello' => '{$folder} module'];\n",
    ];

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname($root.DIRECTORY_SEPARATOR.$path));
        File::put($root.DIRECTORY_SEPARATOR.$path, $contents);
    }

    require_once $root.DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.$folder.'ServiceProvider.php';

    writeManifest($modulesPath, $folder, array_merge([
        'name' => $folder,
        'slug' => $slug,
        'version' => '1.0.0',
        'provider' => $provider,
    ], $manifest));

    return $slug;
}
