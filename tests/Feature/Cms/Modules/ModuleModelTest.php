<?php

use App\Cms\Modules\Module;
use Illuminate\Database\UniqueConstraintViolationException;

it('stores a discovered module as not installed and disabled by default', function () {
    $module = Module::create(['slug' => 'blog', 'name' => 'Blog', 'version' => '1.0.0'])->fresh();

    expect($module->isInstalled())->toBeFalse()
        ->and($module->enabled)->toBeFalse()
        ->and($module->is_core)->toBeFalse()
        ->and($module->is_missing)->toBeFalse()
        ->and($module->installed_version)->toBeNull()
        ->and($module->sort_order)->toBe(0);
});

it('requires a unique slug', function () {
    Module::factory()->create(['slug' => 'blog']);
    Module::factory()->create(['slug' => 'blog']);
})->throws(UniqueConstraintViolationException::class);

it('casts flags and dates', function () {
    $module = Module::factory()->core()->installed()->create()->fresh();

    expect($module->is_core)->toBeTrue()
        ->and($module->enabled)->toBeTrue()
        ->and($module->installed_at)->toBeInstanceOf(DateTimeInterface::class);
});

it('scopes installed and active modules', function () {
    Module::factory()->create(['slug' => 'discovered']);
    Module::factory()->installed()->create(['slug' => 'enabled']);
    Module::factory()->disabled()->create(['slug' => 'disabled']);
    Module::factory()->installed()->missing()->create(['slug' => 'gone']);

    expect(Module::installed()->orderBy('slug')->pluck('slug')->all())->toBe(['disabled', 'enabled', 'gone'])
        ->and(Module::active()->pluck('slug')->all())->toBe(['enabled']);
});

it('orders by sort order, then name', function () {
    Module::factory()->create(['slug' => 'c', 'name' => 'C', 'sort_order' => 1]);
    Module::factory()->create(['slug' => 'b', 'name' => 'B', 'sort_order' => 0]);
    Module::factory()->create(['slug' => 'a', 'name' => 'A', 'sort_order' => 1]);

    expect(Module::ordered()->pluck('slug')->all())->toBe(['b', 'a', 'c']);
});

it('reports whether it is active', function (array $state, bool $active) {
    expect((new Module($state))->isActive())->toBe($active);
})->with([
    'not installed' => [['enabled' => true, 'is_missing' => false], false],
    'enabled' => [['installed_at' => now(), 'enabled' => true, 'is_missing' => false], true],
    'disabled' => [['installed_at' => now(), 'enabled' => false, 'is_missing' => false], false],
    'folder missing' => [['installed_at' => now(), 'enabled' => true, 'is_missing' => true], false],
]);

it('detects when an upgrade is available', function (string $manifest, ?string $installed, bool $expected) {
    $module = new Module([
        'version' => $manifest,
        'installed_version' => $installed,
        'installed_at' => $installed ? now() : null,
    ]);

    expect($module->needsUpgrade())->toBe($expected);
})->with([
    'newer patch' => ['1.0.1', '1.0.0', true],
    'newer minor' => ['1.2.0', '1.1.9', true],
    'double-digit minor' => ['1.10.0', '1.9.0', true],
    'same version' => ['1.0.0', '1.0.0', false],
    'older manifest' => ['1.0.0', '1.1.0', false],
    'not installed' => ['2.0.0', null, false],
]);
