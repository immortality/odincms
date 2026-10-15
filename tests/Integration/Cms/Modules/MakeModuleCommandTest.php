<?php

use App\Cms\Modules\Manifest;
use App\Cms\Modules\ModuleLoader;
use App\Cms\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

// Generates into the real modules/ folder, because generated classes are autoloaded from there
// (Modules\ → modules/). Every folder created here is removed after the test.

afterEach(function () {
    foreach (['GenCheck', 'TourBookings', 'GenForce'] as $folder) {
        File::deleteDirectory(base_path("modules/{$folder}"));
    }
});

it('generates a module that can be synced, installed and used at /admin/{slug}', function () {
    $this->artisan('cms:make:module', ['name' => 'GenCheck'])
        ->expectsOutputToContain('Module "GenCheck" created')
        ->expectsOutputToContain('php artisan cms:module:install gen-check')
        ->assertSuccessful();

    $manifest = Manifest::fromFile(base_path('modules/GenCheck/module.json'));
    expect($manifest->slug)->toBe('gen-check')
        ->and($manifest->provider)->toBe('Modules\\GenCheck\\Providers\\GenCheckServiceProvider')
        ->and($manifest->permissions())->toBe(['gen-check.view', 'gen-check.create', 'gen-check.edit', 'gen-check.delete']);

    $modules = app(ModuleManager::class);
    expect($modules->sync()->added)->toContain('gen-check');
    $modules->install('gen-check');
    app(ModuleLoader::class)->load();

    expect(Schema::hasTable('gen_checks'))->toBeTrue()
        ->and(Permission::where('name', 'like', 'gen-check.%')->orderBy('name')->pluck('name')->all())
        ->toBe(['gen-check.create', 'gen-check.delete', 'gen-check.edit', 'gen-check.view']);

    // Guests are sent to the login page; a user without permissions is forbidden.
    $this->get('/admin/gen-check')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/admin/gen-check')->assertForbidden();

    // Admins see the list and can create items.
    $this->actingAsAdmin()->get('/admin/gen-check')->assertOk()->assertSee('Gen Check');
    $this->actingAsAdmin()->post('/admin/gen-check', ['title' => ['en' => 'Generated item'], 'is_active' => '1'])->assertRedirect();
    $this->actingAsAdmin()->get('/admin/gen-check')->assertSee('Generated item');

    // A group with only gen-check.view sees the list but cannot create.
    $this->actingAsUserWith(['gen-check.view'])->get('/admin/gen-check')->assertOk();
    $this->actingAsUserWith(['gen-check.view'])->post('/admin/gen-check', ['title' => ['en' => 'Nope']])->assertForbidden();

    $modules->uninstall('gen-check');
    expect(Schema::hasTable('gen_checks'))->toBeFalse()
        ->and(Permission::where('name', 'like', 'gen-check.%')->count())->toBe(0);
});

it('names everything after a multi-word module', function () {
    $this->artisan('cms:make:module', ['name' => 'TourBookings'])->assertSuccessful();

    $root = base_path('modules/TourBookings');
    $migration = File::glob($root.'/Database/Migrations/*_create_tour_bookings_table.php');

    expect(File::exists($root.'/Models/TourBooking.php'))->toBeTrue()
        ->and(File::exists($root.'/Http/Controllers/Admin/TourBookingController.php'))->toBeTrue()
        ->and(File::exists($root.'/Providers/TourBookingsServiceProvider.php'))->toBeTrue()
        ->and(File::exists($root.'/Tests/Feature/TourBookingAdminTest.php'))->toBeTrue()
        ->and($migration)->toHaveCount(1)
        ->and(File::get($root.'/Models/TourBooking.php'))->toContain("#[Table('tour_bookings')]")
        ->and(File::get($root.'/Routes/admin.php'))->toContain('use Modules\\TourBookings\\Http\\Controllers\\Admin\\TourBookingController;')
        ->and(File::get($root.'/Resources/lang/en/admin.php'))->toContain("'title' => 'Tour Bookings'")
        ->and(File::get($root.'/module.json'))->toContain('"route": "admin.tour-bookings.index"');

    // No placeholder is left anywhere.
    foreach (File::allFiles($root) as $file) {
        expect($file->getContents())->not->toMatch('/\{\{(Name|Title|slug|namespace|Model|table)\}\}/');
        expect($file->getRelativePathname())->not->toContain('{{');
    }
});

it('rejects a name that is not StudlyCase', function (string $name) {
    $this->artisan('cms:make:module', ['name' => $name])
        ->expectsOutputToContain('must be StudlyCase')
        ->assertFailed();
})->with(['blog', 'tour-bookings', 'Tour_Bookings', '1Blog']);

it('does not overwrite an existing module unless forced', function () {
    $this->artisan('cms:make:module', ['name' => 'GenForce'])->assertSuccessful();
    File::put(base_path('modules/GenForce/module.json'), '{}');

    $this->artisan('cms:make:module', ['name' => 'GenForce'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();
    expect(File::get(base_path('modules/GenForce/module.json')))->toBe('{}');

    $this->artisan('cms:make:module', ['name' => 'GenForce', '--force' => true])->assertSuccessful();
    expect(File::get(base_path('modules/GenForce/module.json')))->toContain('"slug": "gen-force"');
});
