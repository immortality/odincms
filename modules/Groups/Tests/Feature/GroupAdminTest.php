<?php

use App\Models\User;
use Modules\Groups\Database\Seeders\GroupsDatabaseSeeder;
use Modules\Groups\Models\Group;

beforeEach(function () {
    $this->useModule('groups');
    $this->seed(GroupsDatabaseSeeder::class);
});

it('seeds the built-in groups with titles, and gives admins admin.access', function () {
    expect(Group::orderBy('id')->pluck('name')->all())->toBe(['super-admin', 'admin', 'user'])
        ->and(Group::findByName('admin')->label())->toBe('Administrators')
        ->and(Group::findByName('admin')->hasPermissionTo('admin.access'))->toBeTrue();

    app()->setLocale('ru');
    expect(Group::findByName('user')->label())->toBe('Пользователи');
});

it('seeds idempotently', function () {
    $this->seed(GroupsDatabaseSeeder::class);

    expect(Group::count())->toBe(3);
});

it('lists groups with their user counts', function () {
    User::factory()->count(2)->create()->each->assignRole('admin');

    $this->actingAsAdmin()->get(route('admin.groups.index'))
        ->assertOk()
        ->assertSeeInOrder(['Super admins', 'super-admin', '1', 'Administrators', 'admin', '2'])
        ->assertSee('Built-in');
});

it('needs groups.view to see the list', function () {
    $this->actingAsUserWith([])->get(route('admin.groups.index'))->assertForbidden();
    $this->actingAsUserWith(['groups.view'])->get(route('admin.groups.index'))->assertOk();
});

it('creates a group', function () {
    $this->actingAsAdmin()
        ->post(route('admin.groups.store'), ['name' => 'editors', 'title' => ['en' => 'Editors', 'ru' => 'Редакторы']])
        ->assertRedirect()
        ->assertSessionHas('success');

    $group = Group::findByName('editors');
    expect($group->getTranslations('title'))->toBe(['en' => 'Editors', 'ru' => 'Редакторы'])
        ->and($group->guard_name)->toBe('web');
});

it('validates the identifier and the title', function (array $input, string $error) {
    $this->actingAsAdmin()
        ->post(route('admin.groups.store'), $input)
        ->assertSessionHasErrors($error);
})->with([
    'missing identifier' => [['title' => ['en' => 'Editors']], 'name'],
    'uppercase identifier' => [['name' => 'Editors', 'title' => ['en' => 'Editors']], 'name'],
    'identifier with spaces' => [['name' => 'tour guides', 'title' => ['en' => 'Guides']], 'name'],
    'identifier taken' => [['name' => 'admin', 'title' => ['en' => 'Admins']], 'name'],
    'missing default title' => [['name' => 'editors', 'title' => ['ru' => 'Редакторы']], 'title.en'],
]);

it('renames a group it created, but never a built-in group', function () {
    $editors = Group::create(['name' => 'editors', 'title' => ['en' => 'Editors'], 'guard_name' => 'web']);
    $admin = Group::findByName('admin');

    $this->actingAsAdmin()->put(route('admin.groups.update', $editors), ['name' => 'writers', 'title' => ['en' => 'Writers']]);
    $this->actingAsAdmin()->put(route('admin.groups.update', $admin), ['name' => 'boss', 'title' => ['en' => 'Admins']])
        ->assertSessionHasNoErrors();

    expect($editors->fresh()->name)->toBe('writers')
        ->and($admin->fresh()->name)->toBe('admin')
        ->and($admin->fresh()->label())->toBe('Admins');
});

it('shows the identifier of a built-in group as read-only', function () {
    $this->actingAsAdmin()->get(route('admin.groups.edit', Group::findByName('admin')))
        ->assertOk()
        ->assertSee('Built-in groups keep their identifier.')
        ->assertDontSee('name="name"', false);
});

it('deletes a group; its users lose it', function () {
    $editors = Group::create(['name' => 'editors', 'guard_name' => 'web']);
    $user = User::factory()->create()->assignRole('editors');

    $this->actingAsAdmin()->delete(route('admin.groups.destroy', $editors))->assertRedirect(route('admin.groups.index'));

    expect(Group::where('name', 'editors')->exists())->toBeFalse()
        ->and($user->fresh()->hasRole('editors'))->toBeFalse();
});

it('never deletes a built-in group, not even for a super admin', function (string $name) {
    $this->actingAsAdmin()->delete(route('admin.groups.destroy', Group::findByName($name)))->assertForbidden();

    expect(Group::where('name', $name)->exists())->toBeTrue();
})->with(['super-admin', 'admin', 'user']);

it('needs groups.create, groups.edit and groups.delete for the changes', function () {
    $editors = Group::create(['name' => 'editors', 'guard_name' => 'web']);

    $this->actingAsUserWith(['groups.view'])->post(route('admin.groups.store'), ['name' => 'x', 'title' => ['en' => 'X']])->assertForbidden();
    $this->actingAsUserWith(['groups.view'])->put(route('admin.groups.update', $editors), ['name' => 'y', 'title' => ['en' => 'Y']])->assertForbidden();
    $this->actingAsUserWith(['groups.view'])->delete(route('admin.groups.destroy', $editors))->assertForbidden();
});
