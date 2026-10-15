<?php

use App\Cms\Admin\AdminMenu;
use App\Cms\Modules\Module;
use App\Models\User;
use Illuminate\Support\Facades\Lang;
use Modules\Groups\Database\Seeders\GroupsDatabaseSeeder;
use Modules\Groups\Models\Group;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->useModule('groups');
    $this->useModule('permissions');
    $this->seed(GroupsDatabaseSeeder::class);

    foreach (['blog.view', 'blog.publish', 'pages.view'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    // Real modules since Phase 6, so already synced by useModule().
    foreach (['blog' => 'Blog', 'pages' => 'Pages'] as $slug => $name) {
        Module::firstOrCreate(['slug' => $slug], Module::factory()->raw(['slug' => $slug, 'name' => $name]));
    }
});

it('shows the permissions grouped by module, with a column per group', function () {
    $this->actingAsAdmin()->get(route('admin.permissions.index'))
        ->assertOk()
        ->assertSeeInOrder(['Super admins', 'Administrators', 'Users'])
        ->assertSeeInOrder(['Admin panel', 'Sign in to the admin panel', 'Blog', 'Publish', 'blog.publish', 'Pages', 'pages.view'])
        ->assertSee('always all');
});

it('uses a module\'s own permission labels when it has them', function () {
    Lang::addLines(['permissions.publish' => 'Publish posts'], 'en', 'blog');

    $this->actingAsAdmin()->get(route('admin.permissions.index'))->assertSee('Publish posts');
});

it('ticks what each group has; super admins always have everything and cannot be changed', function () {
    Group::findByName('user')->givePermissionTo('blog.view');

    $html = $this->actingAsAdmin()->get(route('admin.permissions.index'))->getContent();
    $user = Group::findByName('user');
    $super = Group::findByName('super-admin');

    expect($html)->toMatch('/id="perm-'.$user->id.'-blogview"[^>]*name="matrix\['.$user->id.'\]\[\]"[^>]*checked/s')
        ->and($html)->toMatch('/id="perm-'.$super->id.'-pagesview"[^>]*checked[^>]*disabled/s')
        ->and($html)->not->toContain('name="matrix['.$super->id.'][]"');
});

it('saves the matrix and leaves super admins alone', function () {
    $admin = Group::findByName('admin');
    $user = Group::findByName('user');
    $super = Group::findByName('super-admin');

    $this->actingAsAdmin()->put(route('admin.permissions.update'), [
        'matrix' => [
            $admin->id => ['admin.access', 'blog.view', 'blog.publish'],
            $super->id => [], // ignored
            // $user missing = no permissions
        ],
    ])->assertRedirect()->assertSessionHas('success', 'The permissions have been saved.');

    expect($admin->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['admin.access', 'blog.publish', 'blog.view'])
        ->and($user->fresh()->permissions)->toBeEmpty();
});

it('ignores permissions that do not exist', function () {
    $admin = Group::findByName('admin');

    $this->actingAsAdmin()->put(route('admin.permissions.update'), ['matrix' => [$admin->id => ['admin.access', 'made.up']]]);

    expect($admin->fresh()->permissions->pluck('name')->all())->toBe(['admin.access'])
        ->and(Permission::where('name', 'made.up')->exists())->toBeFalse();
});

it('takes effect immediately, including the admin menu', function () {
    $editor = User::factory()->create()->assignRole('admin');
    $admin = Group::findByName('admin');
    Permission::findOrCreate('permissions.view', 'web');

    $menu = fn () => collect(app(AdminMenu::class)->build($editor->fresh()))->flatMap(fn ($group) => array_column($group['items'], 'label'))->all();
    expect($editor->can('blog.view'))->toBeFalse()
        ->and($menu())->not->toContain('Permissions');

    $this->actingAsAdmin()->put(route('admin.permissions.update'), ['matrix' => [$admin->id => ['admin.access', 'blog.view', 'permissions.view']]]);

    expect($editor->fresh()->can('blog.view'))->toBeTrue()
        ->and($menu())->toContain('Permissions');
});

it('needs permissions.view to look and permissions.edit to save', function () {
    $this->actingAsUserWith([])->get(route('admin.permissions.index'))->assertForbidden();

    $this->actingAsUserWith(['permissions.view'])->get(route('admin.permissions.index'))
        ->assertOk()
        ->assertDontSee('Save permissions');
    $this->actingAsUserWith(['permissions.view'])->put(route('admin.permissions.update'), ['matrix' => []])->assertForbidden();

    $this->actingAsUserWith(['permissions.view', 'permissions.edit'])->get(route('admin.permissions.index'))->assertSee('Save permissions');
});
