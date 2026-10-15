<?php

use App\Models\User;
use Modules\Groups\Database\Seeders\GroupsDatabaseSeeder;
use Modules\Users\Support\UserGuard;

beforeEach(function () {
    $this->useModule('groups');
    $this->useModule('users');
    $this->seed(GroupsDatabaseSeeder::class);
    $this->guard = app(UserGuard::class);
});

it('knows the last active super admin', function () {
    $first = User::factory()->create()->assignRole('super-admin');
    expect($this->guard->isLastSuperAdmin($first))->toBeTrue();

    $second = User::factory()->create()->assignRole('super-admin');
    expect($this->guard->isLastSuperAdmin($first))->toBeFalse();

    // A deactivated super admin doesn't count.
    $second->forceFill(['is_active' => false])->save();
    expect($this->guard->isLastSuperAdmin($first))->toBeTrue()
        ->and($this->guard->isLastSuperAdmin($second))->toBeFalse();

    // Someone who isn't a super admin is never "the last one".
    expect($this->guard->isLastSuperAdmin(User::factory()->create()))->toBeFalse();
});

it('refuses to delete, deactivate or demote the last active super admin, even for a super admin', function () {
    $last = User::factory()->create()->assignRole('super-admin');
    // A super admin who is deactivated, acting (as a direct call, since inactive users cannot open the admin).
    $actor = User::factory()->create(['is_active' => false]);
    $actor->forceFill(['is_active' => false])->save();
    $actor->assignRole('super-admin');

    expect($this->guard->deleteError($actor, $last))->toBe('This is the last active super admin. Make another user a super admin first.')
        ->and($this->guard->deactivateError($actor, $last))->toBe('This is the last active super admin. Make another user a super admin first.')
        ->and($this->guard->groupsError($actor, $last, ['admin']))->toBe('This is the last active super admin. Make another user a super admin first.')
        ->and($this->guard->groupsError($actor, $last, ['super-admin', 'admin']))->toBeNull();
});

it('lets a super admin manage another super admin when one remains', function () {
    $actor = User::factory()->create()->assignRole('super-admin');
    $other = User::factory()->create()->assignRole('super-admin');

    expect($this->guard->deleteError($actor, $other))->toBeNull()
        ->and($this->guard->deactivateError($actor, $other))->toBeNull()
        ->and($this->guard->groupsError($actor, $other, ['admin']))->toBeNull();
});

it('only lets super admins grant or revoke the Super admins group', function () {
    $editor = User::factory()->create()->assignRole('admin');
    $member = User::factory()->create()->assignRole('user');
    $boss = User::factory()->create()->assignRole('super-admin');
    User::factory()->create()->assignRole('super-admin');

    $message = 'Only super admins can change super admins or the Super admins group.';
    expect($this->guard->groupsError($editor, null, ['super-admin']))->toBe($message)
        ->and($this->guard->groupsError($editor, $member, ['super-admin']))->toBe($message)
        ->and($this->guard->groupsError($editor, $boss, ['admin']))->toBe($message)
        ->and($this->guard->groupsError($editor, $member, ['admin']))->toBeNull()
        ->and($this->guard->groupsError($boss, $member, ['super-admin']))->toBeNull();
});
