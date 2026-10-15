<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('is active by default, with no locale and no last login', function () {
    $user = User::factory()->create()->fresh();

    expect($user->is_active)->toBeTrue()
        ->and($user->locale)->toBeNull()
        ->and($user->last_login_at)->toBeNull();
});

it('casts the CMS columns', function () {
    $user = User::factory()->inactive()->create(['locale' => 'ru']);
    $user->forceFill(['last_login_at' => '2026-10-01 12:00:00'])->save();
    $user = $user->fresh();

    expect($user->is_active)->toBeFalse()
        ->and($user->locale)->toBe('ru')
        ->and($user->last_login_at)->toBeInstanceOf(DateTimeInterface::class);
});

it('does not mass-assign is_active or last_login_at', function () {
    $user = User::factory()->create();

    $user->update(['is_active' => false, 'last_login_at' => now()]);

    expect($user->fresh()->is_active)->toBeTrue()
        ->and($user->fresh()->last_login_at)->toBeNull();
});

it('hides the password and two-factor secrets when serialized', function () {
    $user = User::factory()->create();
    $user->forceFill(['two_factor_secret' => 'secret', 'two_factor_recovery_codes' => 'codes'])->save();

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']);
});

it('must verify its email address', function () {
    expect(User::factory()->unverified()->create())->toBeInstanceOf(MustVerifyEmail::class)
        ->and(User::factory()->unverified()->create()->hasVerifiedEmail())->toBeFalse();
});

it('has roles and permissions (spatie)', function () {
    $role = Role::create(['name' => 'editor']);
    $role->givePermissionTo(Permission::create(['name' => 'blog.view']));

    $user = User::factory()->create();
    $user->assignRole('editor');

    expect($user->hasRole('editor'))->toBeTrue()
        ->and($user->can('blog.view'))->toBeTrue()
        ->and($user->can('blog.delete'))->toBeFalse();
});

it('supports two-factor authentication (fortify)', function () {
    $user = User::factory()->create();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('stores one avatar with a 128x128 thumbnail', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $user->addMedia(UploadedFile::fake()->image('first.jpg', 400, 300))->toMediaCollection('avatar');
    $user->addMedia(UploadedFile::fake()->image('second.png', 400, 300))->toMediaCollection('avatar');

    $user = $user->fresh();
    $media = $user->getFirstMedia('avatar');

    expect($user->getMedia('avatar'))->toHaveCount(1)
        ->and($media->file_name)->toBe('second.png')
        ->and($media->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and($user->avatarUrl())->toContain('conversions');

    [$width, $height] = getimagesize($media->getPath('thumb'));
    expect([$width, $height])->toBe([128, 128]);
});

it('accepts only images as avatar', function () {
    Storage::fake('public');

    User::factory()->create()
        ->addMedia(UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->toMediaCollection('avatar');
})->throws(FileUnacceptableForCollection::class);

it('has no avatar URL without an avatar', function () {
    expect(User::factory()->create()->avatarUrl())->toBeNull();
});

it('makes initials from the name', function (string $name, string $initials) {
    expect((new User(['name' => $name]))->initials())->toBe($initials);
})->with([
    ['Aibek Asanov', 'AA'],
    ['elena', 'E'],
    ['  Jean  Claude  Van Damme ', 'JC'],
    ['Айгүл Токтогулова', 'АТ'],
]);
