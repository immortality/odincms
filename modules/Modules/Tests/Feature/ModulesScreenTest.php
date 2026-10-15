<?php

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Load the Modules screen itself from the real modules folder...
    $this->useModule('modules');

    // ...then let it manage temporary modules (no migrations, so no schema changes in tests).
    $this->modulesPath = storage_path('framework/testing/screen-'.uniqid());
    File::ensureDirectoryExists($this->modulesPath);
    config(['cms.modules_path' => $this->modulesPath, 'cms.core_modules' => ['users']]);
    app()->forgetInstance(ModuleManager::class);

    fakeModule($this->modulesPath, 'Tags', ['name' => 'Tags', 'description' => 'Tag anything', 'permissions' => ['tags.view']]);
    fakeModule($this->modulesPath, 'Blog', ['name' => 'Blog', 'requires' => ['tags']]);
    fakeModule($this->modulesPath, 'Users', ['name' => 'Users']);
    app(ModuleManager::class)->sync();
});

afterEach(function () {
    File::deleteDirectory($this->modulesPath);
});

function moduleRow(string $html, string $slug): string
{
    preg_match('/<tr id="module-'.$slug.'">(.*?)<\/tr>/s', $html, $match);

    return $match[1] ?? '';
}

it('lists the modules with status, version, dependencies and only the allowed buttons', function () {
    $html = $this->actingAsAdmin()->get(route('admin.modules.index'))->assertOk()->getContent();

    $tags = moduleRow($html, 'tags');
    $blog = moduleRow($html, 'blog');

    expect($tags)->toContain('Tag anything')->toContain('Not installed')->toContain('Install')
        ->and($blog)->toContain('Not installed')->toContain('tags')->not->toContain('Install</span>');
});

it('installs, disables, enables and uninstalls a module', function () {
    $this->actingAsAdmin();

    $this->post(route('admin.modules.action', ['tags', 'install']))
        ->assertRedirect(route('admin.modules.index'))
        ->assertSessionHas('success', 'Tags has been installed and enabled.');
    expect(Module::where('slug', 'tags')->sole()->isActive())->toBeTrue()
        ->and(Permission::where('name', 'tags.view')->exists())->toBeTrue();

    $this->post(route('admin.modules.action', ['tags', 'disable']))->assertSessionHas('success', 'Tags has been disabled. Its data is kept.');
    expect(Module::where('slug', 'tags')->sole()->enabled)->toBeFalse();

    $this->post(route('admin.modules.action', ['tags', 'enable']))->assertSessionHas('success', 'Tags has been enabled.');
    $this->post(route('admin.modules.action', ['tags', 'uninstall']))->assertSessionHas('success', 'Tags has been uninstalled.');
    expect(Module::where('slug', 'tags')->sole()->isInstalled())->toBeFalse();
});

it('shows the reason when an action is refused', function () {
    $this->actingAsAdmin()->post(route('admin.modules.action', ['blog', 'install']))
        ->assertSessionHas('error', 'Module "blog" requires "tags" to be installed and enabled first.');

    expect(Module::where('slug', 'blog')->sole()->isInstalled())->toBeFalse();
});

it('never offers disable or uninstall for a core module', function () {
    app(ModuleManager::class)->install('users');

    $users = moduleRow($this->actingAsAdmin()->get(route('admin.modules.index'))->getContent(), 'users');

    expect($users)->toContain('Core')->toContain('Enabled')->not->toContain('Disable')->not->toContain('Uninstall');
    $this->post(route('admin.modules.action', ['users', 'uninstall']))
        ->assertSessionHas('error', 'Module "users" is a core module and cannot be uninstalled.');
});

it('offers an upgrade when the module folder has a newer version', function () {
    app(ModuleManager::class)->install('tags');
    fakeModule($this->modulesPath, 'Tags', ['name' => 'Tags', 'version' => '1.1.0', 'permissions' => ['tags.view']]);

    $html = $this->actingAsAdmin()->get(route('admin.modules.index'))->getContent();
    expect($html)->toContain('The modules folder has changed.');

    $this->post(route('admin.modules.sync'))->assertSessionHas('success', 'Updated: tags.');
    expect(moduleRow($this->get(route('admin.modules.index'))->getContent(), 'tags'))->toContain('1.1.0 available')->toContain('Upgrade');

    $this->post(route('admin.modules.action', ['tags', 'upgrade']))->assertSessionHas('success', 'Tags has been upgraded.');
    expect(Module::where('slug', 'tags')->sole()->installed_version)->toBe('1.1.0');
});

it('syncs new module folders', function () {
    fakeModule($this->modulesPath, 'Gallery', ['name' => 'Gallery']);

    $this->actingAsAdmin()->post(route('admin.modules.sync'))->assertSessionHas('success', 'New: gallery.');

    expect(Module::where('slug', 'gallery')->exists())->toBeTrue();
});

it('shows modules whose module.json cannot be read', function () {
    writeManifest($this->modulesPath, 'Broken', '{ nope');

    $this->actingAsAdmin()->get(route('admin.modules.index'))->assertSee('invalid JSON');
});

it('needs modules.view to look, modules.manage to change and modules.uninstall to uninstall', function () {
    app(ModuleManager::class)->install('tags');

    $this->actingAsUserWith([])->get(route('admin.modules.index'))->assertForbidden();

    $viewer = $this->actingAsUserWith(['modules.view']);
    $html = $viewer->get(route('admin.modules.index'))->assertOk()->getContent();
    expect(moduleRow($html, 'tags'))->not->toContain('Disable')
        ->and($html)->not->toContain(route('admin.modules.sync'));
    $viewer->post(route('admin.modules.action', ['tags', 'disable']))->assertForbidden();
    $viewer->post(route('admin.modules.sync'))->assertForbidden();

    $manager = $this->actingAsUserWith(['modules.view', 'modules.manage']);
    expect(moduleRow($manager->get(route('admin.modules.index'))->getContent(), 'tags'))->toContain('Disable')->not->toContain('Uninstall');
    $manager->post(route('admin.modules.action', ['tags', 'uninstall']))->assertForbidden();
});

it('rejects unknown actions', function () {
    $this->actingAsAdmin()->post('/admin/modules/tags/explode')->assertNotFound();
});
