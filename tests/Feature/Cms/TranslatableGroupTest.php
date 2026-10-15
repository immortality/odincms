<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * <x-admin.form.translatable-group>: several translatable fields under one set of language tabs.
 */
beforeEach(function () {
    config(['cms.locales' => ['en', 'ru', 'ky'], 'cms.default_locale' => 'en']);
});

function renderGroup(array $errors = []): string
{
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag($errors)));

    return Blade::render(<<<'BLADE'
        <x-admin.form.translatable-group :fields="[
            ['name' => 'title', 'label' => 'Title', 'value' => ['en' => 'Riding', 'ru' => 'Прогулки'], 'required' => true],
            ['name' => 'detail', 'label' => 'Detail', 'type' => 'textarea', 'value' => ['en' => 'Two hours']],
        ]" />
        BLADE);
}

/**
 * The HTML of one language's tab pane.
 */
function groupPane(string $html, string $locale): string
{
    preg_match('/<div class="tab-pane[^"]*" id="[^"]+-'.$locale.'".*?(?=<div class="tab-pane|$)/s', $html, $match);

    return $match[0] ?? '';
}

it('puts all the fields of one language in that language\'s tab', function () {
    $html = renderGroup();

    expect(substr_count($html, 'role="tab"'))->toBe(3)
        ->and(groupPane($html, 'en'))->toContain('name="title[en]"', 'value="Riding"', 'name="detail[en]"', 'Two hours')
        ->and(groupPane($html, 'ru'))->toContain('name="title[ru]"', 'value="Прогулки"', 'name="detail[ru]"')
        ->and(groupPane($html, 'ru'))->not->toContain('title[en]');
});

it('requires the default language only', function () {
    $html = renderGroup();

    expect(groupPane($html, 'en'))->toContain('required')
        ->and(groupPane($html, 'ru'))->not->toContain('required');
});

it('opens and marks the tab with a validation error', function () {
    $html = renderGroup(['detail.ru' => 'Too long.']);

    expect($html)->toMatch('/class="tab-link active has-error"[^>]*data-bs-target="#[^"]+-ru"/')
        ->and(groupPane($html, 'ru'))->toContain('tab-pane active', 'Too long.');
});
