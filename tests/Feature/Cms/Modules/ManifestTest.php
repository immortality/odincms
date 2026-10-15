<?php

use App\Cms\Modules\Exceptions\InvalidManifestException;
use App\Cms\Modules\Manifest;
use Illuminate\Support\Facades\File;
use Tests\Fixtures\Modules\FakeModuleServiceProvider;
use Tests\Fixtures\Modules\NotAServiceProvider;

beforeEach(function () {
    $this->modulesPath = storage_path('framework/testing/manifests-'.uniqid());
});

afterEach(function () {
    File::deleteDirectory($this->modulesPath);
});

it('reads a valid manifest', function () {
    $file = writeManifest($this->modulesPath, 'Blog', validManifest());

    $manifest = Manifest::fromFile($file);

    expect($manifest->name)->toBe('Blog')
        ->and($manifest->slug)->toBe('blog')
        ->and($manifest->version)->toBe('1.0.0')
        ->and($manifest->provider)->toBe(FakeModuleServiceProvider::class)
        ->and($manifest->isCore())->toBeFalse()
        ->and($manifest->requires())->toBe(['categories'])
        ->and($manifest->permissions())->toBe(['blog.view', 'blog.create', 'blog.publish'])
        ->and($manifest->widgets())->toBe(['Modules\\Blog\\Widgets\\LatestPostsWidget'])
        ->and($manifest->adminMenu()['items'][0]['route'])->toBe('admin.blog.posts.index')
        ->and($manifest->path())->toBe(dirname($file))
        ->and($manifest->path('Routes/admin.php'))->toBe(dirname($file).DIRECTORY_SEPARATOR.'Routes/admin.php')
        ->and($manifest->folder())->toBe('Blog')
        ->and($manifest->namespace())->toBe('Modules\\Blog');
});

it('applies defaults for the optional keys', function () {
    $file = writeManifest($this->modulesPath, 'Blog', [
        'name' => 'Blog', 'slug' => 'blog', 'version' => '1.0.0', 'provider' => FakeModuleServiceProvider::class,
    ]);

    $manifest = Manifest::fromFile($file);

    expect($manifest->isCore())->toBeFalse()
        ->and($manifest->requires())->toBe([])
        ->and($manifest->permissions())->toBe([])
        ->and($manifest->widgets())->toBe([])
        ->and($manifest->adminMenu())->toBe([])
        ->and($manifest->description())->toBe('');
});

it('accepts multi-word slugs that match a StudlyCase folder', function () {
    $file = writeManifest($this->modulesPath, 'TourBookings', validManifest([
        'name' => 'Tour bookings', 'slug' => 'tour-bookings', 'permissions' => ['tour-bookings.view'],
    ]));

    expect(Manifest::fromFile($file)->namespace())->toBe('Modules\\TourBookings');
});

it('translates the description with a fallback to the default locale', function () {
    $manifest = Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', validManifest()));

    expect($manifest->description('ru'))->toBe('Блог')
        ->and($manifest->description('ky'))->toBe('Blog posts')
        ->and($manifest->description())->toBe('Blog posts');
});

it('falls back to any translation when neither the locale nor the default locale exists', function () {
    $manifest = Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', validManifest(['description' => ['ru' => 'Блог']])));

    expect($manifest->description('ky'))->toBe('Блог');
});

it('rejects a missing file', function () {
    Manifest::fromFile($this->modulesPath.'/Nope/module.json');
})->throws(InvalidManifestException::class, 'does not exist');

it('rejects invalid JSON', function () {
    Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', '{ "name": "Blog", }'));
})->throws(InvalidManifestException::class, 'invalid JSON');

it('rejects a JSON array instead of an object', function () {
    Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', '["blog"]'));
})->throws(InvalidManifestException::class, 'must be a JSON object');

it('requires the name, slug, version and provider keys', function (string $key) {
    $data = validManifest();
    unset($data[$key]);

    Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', $data));
})->with(['name', 'slug', 'version', 'provider'])
    ->throws(InvalidManifestException::class, 'is required');

it('rejects a bad slug', function (string $slug) {
    Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', validManifest(['slug' => $slug])));
})->with(['Blog', 'blog_posts', 'blog posts', '-blog', 'blog-', '1blog'])
    ->throws(InvalidManifestException::class, 'kebab-case');

it('rejects a slug that does not match the folder', function () {
    Manifest::fromFile(writeManifest($this->modulesPath, 'News', validManifest()));
})->throws(InvalidManifestException::class, 'does not match the slug "blog" (expected the folder "Blog")');

it('rejects a version that is not semver', function (string $version) {
    Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', validManifest(['version' => $version])));
})->with(['1', '1.0', 'v1.0.0', '1.0.0.0', 'latest'])
    ->throws(InvalidManifestException::class, 'semantic version');

it('accepts pre-release versions', function () {
    $manifest = Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', validManifest(['version' => '2.0.0-beta.1'])));

    expect($manifest->version)->toBe('2.0.0-beta.1');
});

it('rejects an unknown provider class', function () {
    Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', validManifest(['provider' => 'Modules\\Blog\\Providers\\Missing'])));
})->throws(InvalidManifestException::class, 'does not exist');

it('rejects a provider that is not a service provider', function () {
    Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', validManifest(['provider' => NotAServiceProvider::class])));
})->throws(InvalidManifestException::class, 'must extend');

it('rejects a module that requires itself', function () {
    Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', validManifest(['requires' => ['blog']])));
})->throws(InvalidManifestException::class, 'cannot require itself');

it('rejects permissions outside the module prefix', function () {
    Manifest::fromFile(writeManifest($this->modulesPath, 'Blog', validManifest(['permissions' => ['users.delete']])));
})->throws(InvalidManifestException::class, 'must be named "blog.{action}"');

it('rejects wrongly typed optional keys', function (array $override, string $message) {
    $file = writeManifest($this->modulesPath, 'Blog', validManifest($override));

    expect(fn () => Manifest::fromFile($file))->toThrow(InvalidManifestException::class, $message);
})->with([
    'core as string' => [['core' => 'yes'], '"core" must be true or false'],
    'requires as string' => [['requires' => 'categories'], '"requires" must be an array'],
    'permissions with a number' => [['permissions' => [1]], '"permissions" must be an array of non-empty strings'],
    'description as number' => [['description' => 5], '"description" must be a string'],
    'admin_menu as list' => [['admin_menu' => ['x']], '"admin_menu" must be an object'],
    'menu item without route' => [['admin_menu' => ['items' => [['label' => 'Posts']]]], '"admin_menu.items.0.route" is required'],
    'menu order as string' => [['admin_menu' => ['order' => 'first']], '"admin_menu.order" must be an integer'],
]);

it('names the file in the error message', function () {
    $file = writeManifest($this->modulesPath, 'Blog', validManifest(['version' => 'x']));

    expect(fn () => Manifest::fromFile($file))
        ->toThrow(fn (InvalidManifestException $e) => expect($e->getMessage())->toContain('Blog'.DIRECTORY_SEPARATOR.'module.json'));
});
