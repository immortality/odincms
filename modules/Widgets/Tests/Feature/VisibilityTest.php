<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Modules\Groups\Database\Seeders\GroupsDatabaseSeeder;
use Modules\Widgets\Support\Visibility;

beforeEach(function () {
    $this->useModule('groups');
    $this->seed(GroupsDatabaseSeeder::class);
});

function requestFor(string $path, ?string $routeName = null): Request
{
    $request = Request::create($path);
    if ($routeName !== null) {
        $route = (new RoutingRoute('GET', $path, fn () => ''))->name($routeName);
        $request->setRouteResolver(fn () => $route);
    }

    return $request;
}

it('shows widgets everywhere by default', function () {
    expect(Visibility::allows(null, requestFor('/anything'), null, 'en'))->toBeTrue();
});

it('shows a widget only on matching pages, by route name or path', function (string $path, ?string $route, bool $visible) {
    $rules = ['pages' => ['mode' => 'only', 'patterns' => ['blog.*', 'tours/*', '/']]];

    expect(Visibility::allows($rules, requestFor($path, $route), null, 'en'))->toBe($visible);
})->with([
    'route pattern' => ['/news/hello', 'blog.posts.show', true],
    'path pattern' => ['/tours/kyrgyzstan', null, true],
    'home page' => ['/', 'home', true],
    'other page' => ['/about', 'about', false],
]);

it('hides a widget on the excepted pages', function () {
    $rules = ['pages' => ['mode' => 'except', 'patterns' => ['contacts']]];

    expect(Visibility::allows($rules, requestFor('/contacts'), null, 'en'))->toBeFalse()
        ->and(Visibility::allows($rules, requestFor('/about'), null, 'en'))->toBeTrue();
});

it('limits a widget to some languages', function () {
    $rules = ['locales' => ['ru', 'ky']];

    expect(Visibility::allows($rules, requestFor('/'), null, 'ru'))->toBeTrue()
        ->and(Visibility::allows($rules, requestFor('/'), null, 'en'))->toBeFalse();
});

it('limits a widget to an audience', function () {
    $guest = null;
    $member = User::factory()->create()->assignRole('user');
    $admin = User::factory()->create()->assignRole('admin');
    $check = fn (array $rules, ?User $user) => Visibility::allows($rules, requestFor('/'), $user, 'en');

    expect($check(['audience' => 'guests'], $guest))->toBeTrue()
        ->and($check(['audience' => 'guests'], $member))->toBeFalse()
        ->and($check(['audience' => 'members'], $guest))->toBeFalse()
        ->and($check(['audience' => 'members'], $member))->toBeTrue()
        ->and($check(['audience' => 'groups', 'groups' => ['admin']], $member))->toBeFalse()
        ->and($check(['audience' => 'groups', 'groups' => ['admin']], $admin))->toBeTrue()
        ->and($check(['audience' => 'groups', 'groups' => []], $admin))->toBeFalse();
});
