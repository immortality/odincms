<?php

use App\Cms\Widgets\WidgetRegistry;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\Pages\Models\Page;
use Modules\Pages\Widgets\PageWidget;

beforeEach(function () {
    config(['cms.locales' => ['en', 'ru'], 'cms.default_locale' => 'en', 'app.url' => 'http://localhost']);
    URL::useOrigin('http://localhost');

    $this->useModule('pages');
});

it('is registered by the module', function () {
    expect(app(WidgetRegistry::class)->get('pages.page'))->toBe(PageWidget::class);
});

it('shows the page title, the start of its text and a link by default', function () {
    $page = Page::factory()->published()->create([
        'title' => ['en' => 'About us'], 'slug' => 'about-us', 'body' => ['en' => '<p>We are a <b>small</b> team.</p>'],
    ]);

    $html = (new PageWidget(['page' => (string) $page->id]))->render()->render();

    expect($html)->toContain('<h2 class="section-title">About us</h2>', 'We are a small team.', 'href="http://localhost/about-us"')
        ->not->toContain('check-list');
});

it('uses its own slogan, intro and detail in the current language', function () {
    $page = Page::factory()->published()->create(['title' => ['en' => 'About us']]);
    $widget = new PageWidget([
        'page' => (string) $page->id,
        'slogan' => ['en' => 'Meet our *experts*', 'ru' => 'Наши эксперты'],
        'intro' => ['en' => 'Short intro.'],
        'detail' => ['en' => '<p>Rich <strong>detail</strong></p>'],
        'link' => false,
    ]);

    expect($widget->setTitle('About company')->render()->render())
        ->toContain('<span class="eyebrow">About company</span>', 'Meet our <span class="hl">experts</span>', 'Short intro.', '<p>Rich <strong>detail</strong></p>')
        ->not->toContain('href=')->not->toContain('check-list');

    app()->setLocale('ru');
    expect($widget->render()->render())->toContain('Наши эксперты', 'Short intro.'); // intro falls back to English
});

it('shows nothing for a draft or a missing page, and needs a page', function () {
    $draft = Page::factory()->create();

    expect((new PageWidget(['page' => (string) $draft->id]))->render())->toBe('')
        ->and((new PageWidget(['page' => '999']))->render())->toBe('');

    expect(fn () => (new PageWidget)->validate(['page' => '']))->toThrow(ValidationException::class);
    expect((new PageWidget)->validate(['page' => (string) $draft->id, 'intro' => ['en' => 'Hi']]))
        ->toMatchArray(['page' => (string) $draft->id, 'intro' => ['en' => 'Hi'], 'link' => false])
        ->not->toHaveKey('checklist');
});
