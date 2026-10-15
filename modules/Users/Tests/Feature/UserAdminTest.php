<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Groups\Database\Seeders\GroupsDatabaseSeeder;

beforeEach(function () {
    $this->useModule('groups');
    $this->useModule('users');
    $this->seed(GroupsDatabaseSeeder::class);
    Storage::fake('public');
    Notification::fake();
});

function superAdmin(array $attributes = []): User
{
    return User::factory()->create($attributes)->assignRole('super-admin');
}

describe('list', function () {
    it('lists, searches and filters users', function () {
        User::factory()->create(['name' => 'Aibek Editor', 'email' => 'aibek@example.com'])->assignRole('admin');
        User::factory()->inactive()->create(['name' => 'Old Member', 'email' => 'old@example.com'])->assignRole('user');

        $this->actingAsAdmin()->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Aibek Editor')
            ->assertSee('Administrators')
            ->assertSee('Old Member');

        $this->actingAsAdmin()->get(route('admin.users.index', ['search' => 'aibek@']))
            ->assertSee('Aibek Editor')->assertDontSee('Old Member');
        $this->actingAsAdmin()->get(route('admin.users.index', ['group' => 'user']))
            ->assertSee('Old Member')->assertDontSee('Aibek Editor');
        $this->actingAsAdmin()->get(route('admin.users.index', ['status' => 'inactive']))
            ->assertSee('Old Member')->assertDontSee('Aibek Editor');
    });

    it('needs users.view', function () {
        $this->actingAsUserWith([])->get(route('admin.users.index'))->assertForbidden();
        $this->actingAsUserWith(['users.view'])->get(route('admin.users.index'))->assertOk();
    });
});

describe('create', function () {
    it('creates an active, verified user with a password and groups', function () {
        $this->actingAsAdmin()->post(route('admin.users.store'), [
            'name' => 'New Editor',
            'email' => 'editor@example.com',
            'password' => 'a-Strong-pass-123',
            'password_confirmation' => 'a-Strong-pass-123',
            'groups' => ['admin', 'user'],
            'is_active' => '1',
            'locale' => 'ru',
        ])->assertRedirect()->assertSessionHas('success');

        $user = User::where('email', 'editor@example.com')->sole();
        expect($user->hasVerifiedEmail())->toBeTrue()
            ->and($user->is_active)->toBeTrue()
            ->and($user->locale)->toBe('ru')
            ->and(Hash::check('a-Strong-pass-123', $user->password))->toBeTrue()
            ->and($user->getRoleNames()->sort()->values()->all())->toBe(['admin', 'user']);
        Notification::assertNothingSent();
    });

    it('sends a reset link when no password is given', function () {
        $this->actingAsAdmin()->post(route('admin.users.store'), [
            'name' => 'No Password', 'email' => 'np@example.com', 'is_active' => '1',
        ])->assertSessionHas('success', fn ($message) => str_contains($message, 'reset link has been sent to np@example.com'));

        Notification::assertSentTo(User::where('email', 'np@example.com')->sole(), ResetPassword::class);
    });

    it('validates the input', function (array $input, string $error) {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAsAdmin()->post(route('admin.users.store'), array_merge(['name' => 'X', 'email' => 'x@example.com'], $input))
            ->assertSessionHasErrors($error);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'bad email' => [['email' => 'not-an-email'], 'email'],
        'email taken' => [['email' => 'taken@example.com'], 'email'],
        'password not confirmed' => [['password' => 'a-Strong-pass-123', 'password_confirmation' => 'other'], 'password'],
        'unknown group' => [['groups' => ['nope']], 'groups.0'],
        'unknown locale' => [['locale' => 'de'], 'locale'],
        'avatar not an image' => [['avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], 'avatar'],
    ]);

    it('stores an avatar', function () {
        $this->actingAsAdmin()->post(route('admin.users.store'), [
            'name' => 'With Avatar', 'email' => 'a@example.com', 'avatar' => UploadedFile::fake()->image('me.png', 300, 300),
        ]);

        expect(User::where('email', 'a@example.com')->sole()->avatarUrl())->not->toBeNull();
    });

    it('needs users.create', function () {
        $this->actingAsUserWith(['users.view'])->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.com'])
            ->assertForbidden();
    });
});

describe('update', function () {
    it('updates details, groups and status, and keeps the password when left empty', function () {
        $user = User::factory()->create(['email' => 'u@example.com'])->assignRole('user');
        $oldHash = $user->password;

        $this->actingAsAdmin()->put(route('admin.users.update', $user), [
            'name' => 'Renamed', 'email' => 'u@example.com', 'groups' => ['admin'], 'is_active' => '0',
        ])->assertRedirect(route('admin.users.edit', $user));

        $user->refresh();
        expect($user->name)->toBe('Renamed')
            ->and($user->is_active)->toBeFalse()
            ->and($user->getRoleNames()->all())->toBe(['admin'])
            ->and($user->password)->toBe($oldHash);
    });

    it('replaces and removes the avatar', function () {
        $user = User::factory()->create();
        $base = ['name' => $user->name, 'email' => $user->email, 'is_active' => '1'];

        $this->actingAsAdmin()->put(route('admin.users.update', $user), [...$base, 'avatar' => UploadedFile::fake()->image('a.png')]);
        expect($user->fresh()->avatarUrl())->not->toBeNull();

        $this->actingAsAdmin()->put(route('admin.users.update', $user), [...$base, 'avatar_remove' => '1']);
        expect($user->fresh()->avatarUrl())->toBeNull();
    });

    it('sends a password reset link on request', function () {
        $user = User::factory()->create();

        $this->actingAsAdmin()->post(route('admin.users.reset-link', $user))->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
    });
});

describe('protection against locking out', function () {
    it('does not let users delete or deactivate themselves', function () {
        $me = superAdmin();
        superAdmin(); // not the last super admin

        $this->actingAsAdmin($me)->delete(route('admin.users.destroy', $me))->assertSessionHas('error', 'You cannot delete your own account.');
        $this->actingAsAdmin($me)->put(route('admin.users.update', $me), ['name' => $me->name, 'email' => $me->email, 'groups' => ['super-admin'], 'is_active' => '0'])
            ->assertSessionHasErrors(['is_active' => 'You cannot deactivate your own account.']);

        expect($me->fresh()->is_active)->toBeTrue();
    });

    // The "last active super admin" rules are covered in UserGuardTest: through the admin, the only
    // super admin who could try it is that last super admin, which the self-rules already stop.

    it('does not let a super admin leave the Super admins group', function () {
        $me = superAdmin();
        superAdmin();

        $this->actingAsAdmin($me)->put(route('admin.users.update', $me), ['name' => $me->name, 'email' => $me->email, 'groups' => ['admin'], 'is_active' => '1'])
            ->assertSessionHasErrors(['groups' => 'You cannot remove yourself from the Super admins group.']);
    });
});

describe('super admins are protected from other admins', function () {
    beforeEach(function () {
        $this->target = superAdmin(['name' => 'The Boss']);
        $this->editor = User::factory()->create();
    });

    it('hides the Super admins group from admins who are not super admins', function () {
        $this->actingAsUserWith(['users.view', 'users.create'], $this->editor)->get(route('admin.users.create'))
            ->assertOk()
            ->assertDontSee('Super admins')
            ->assertSee('Administrators');
    });

    it('does not let them make anyone a super admin', function () {
        $this->actingAsUserWith(['users.view', 'users.create'], $this->editor)
            ->post(route('admin.users.store'), ['name' => 'Sneaky', 'email' => 's@example.com', 'groups' => ['super-admin']])
            ->assertSessionHasErrors(['groups' => 'Only super admins can change super admins or the Super admins group.']);

        expect(User::where('email', 's@example.com')->exists())->toBeFalse();
    });

    it('does not let them promote themselves', function () {
        $this->actingAsUserWith(['users.view', 'users.edit'], $this->editor)
            ->put(route('admin.users.update', $this->editor), ['name' => 'Me', 'email' => $this->editor->email, 'groups' => ['super-admin'], 'is_active' => '1'])
            ->assertSessionHasErrors('groups');

        expect($this->editor->fresh()->hasRole('super-admin'))->toBeFalse();
    });

    it('does not let them edit or delete a super admin', function () {
        $this->actingAsUserWith(['users.view', 'users.edit', 'users.delete'], $this->editor);

        $this->get(route('admin.users.edit', $this->target))->assertForbidden();
        $this->put(route('admin.users.update', $this->target), ['name' => 'X', 'email' => $this->target->email])->assertForbidden();
        $this->delete(route('admin.users.destroy', $this->target))->assertForbidden();
    });
});

describe('bulk actions', function () {
    it('deactivates and deletes users, skipping the ones that are not allowed', function () {
        $me = superAdmin(['name' => 'Me']);
        [$a, $b] = User::factory()->count(2)->create();

        $this->actingAsAdmin($me)->post(route('admin.users.bulk'), ['action' => 'deactivate', 'ids' => [$a->id, $b->id, $me->id]])
            ->assertSessionHas('success', '2 users updated.')
            ->assertSessionHas('warning', 'Skipped (not allowed): Me.');

        expect($a->fresh()->is_active)->toBeFalse()->and($me->fresh()->is_active)->toBeTrue();

        $this->actingAsAdmin($me)->post(route('admin.users.bulk'), ['action' => 'delete', 'ids' => [$a->id]]);
        expect(User::find($a->id))->toBeNull();
    });
});

describe('profile', function () {
    it('is available to every admin user, without users permissions', function () {
        $this->actingAsUserWith([])->get(route('admin.users.profile'))
            ->assertOk()
            ->assertSee('My profile')
            ->assertSee('Two-factor authentication');
    });

    it('updates the own name, language and avatar', function () {
        $user = User::factory()->create();

        $this->actingAsUserWith([], $user)->put(route('admin.users.profile.update'), [
            'name' => 'New Name', 'email' => $user->email, 'locale' => 'ky', 'avatar' => UploadedFile::fake()->image('me.jpg'),
        ])->assertSessionHas('success', 'Your profile has been saved.');

        $user->refresh();
        expect($user->name)->toBe('New Name')
            ->and($user->locale)->toBe('ky')
            ->and($user->avatarUrl())->not->toBeNull();
    });

    it('asks to verify a changed email address', function () {
        $user = User::factory()->create();

        $this->actingAsUserWith([], $user)->put(route('admin.users.profile.update'), ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertRedirect(route('verification.notice'));

        expect($user->fresh()->email)->toBe('new@example.com')
            ->and($user->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('changes the own password after checking the current one', function () {
        $user = User::factory()->create();

        $this->actingAsUserWith([], $user)->put(route('admin.users.profile.password'), [
            'current_password' => 'wrong', 'password' => 'a-New-pass-123', 'password_confirmation' => 'a-New-pass-123',
        ])->assertSessionHasErrors('current_password');

        $this->actingAsUserWith([], $user)->put(route('admin.users.profile.password'), [
            'current_password' => 'password', 'password' => 'a-New-pass-123', 'password_confirmation' => 'a-New-pass-123',
        ])->assertSessionHas('success');

        expect(Hash::check('a-New-pass-123', $user->fresh()->password))->toBeTrue();
    });

    it('shows the QR code while two-factor authentication waits for confirmation', function () {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP')])->save();

        $this->actingAsUserWith([], $user)->get(route('admin.users.profile'))
            ->assertSee('<svg', false)
            ->assertSee('JBSWY3DPEHPK3PXP')
            ->assertSee(route('two-factor.confirm'), false);
    });
});
