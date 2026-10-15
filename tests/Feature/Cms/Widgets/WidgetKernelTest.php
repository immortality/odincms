<?php

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleLoader;
use App\Cms\Modules\ModuleManager;
use App\Cms\Support\FormFields;
use App\Cms\Widgets\HtmlWidget;
use App\Cms\Widgets\Widget;
use App\Cms\Widgets\WidgetRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\Widgets\GreetingWidget;

describe('Widget', function () {
    it('reads saved settings, falling back to the field defaults', function () {
        expect((new GreetingWidget)->setting('name'))->toBe('World')
            ->and((new GreetingWidget(['name' => 'Bishkek']))->setting('name'))->toBe('Bishkek')
            ->and((new GreetingWidget)->setting('unknown'))->toBeNull();
    });

    it('validates input with its fields and returns the values to store', function () {
        expect((new GreetingWidget)->validate(['name' => 'Osh', 'shout' => '1']))->toBe(['name' => 'Osh', 'shout' => true]);

        expect(fn () => (new GreetingWidget)->validate(['name' => '']))->toThrow(ValidationException::class);
    });
});

describe('WidgetRegistry', function () {
    it('has the core HTML widget', function () {
        expect(app(WidgetRegistry::class)->get('core.html'))->toBe(HtmlWidget::class);
    });

    it('registers widget types and makes configured widgets', function () {
        $registry = new WidgetRegistry;
        $registry->register(GreetingWidget::class);

        expect($registry->has('test.greeting'))->toBeTrue()
            ->and($registry->make('test.greeting', ['name' => 'X']))->toBeInstanceOf(GreetingWidget::class)
            ->and($registry->make('test.missing'))->toBeNull();
    });

    it('refuses classes that are not widgets, and badly named keys', function () {
        expect(fn () => (new WidgetRegistry)->register(stdClass::class))->toThrow(InvalidArgumentException::class, 'must extend');

        $badKey = new class extends Widget
        {
            public static string $key = 'NoModulePart';

            public static string $name = 'Bad';

            public function render(): string
            {
                return '';
            }
        };

        expect(fn () => (new WidgetRegistry)->register($badKey::class))->toThrow(InvalidArgumentException::class, '{module}.{name}');
    });
});

describe('HtmlWidget', function () {
    it('shows the content in the current language, falling back to the default one', function () {
        $widget = new HtmlWidget(['content' => ['en' => '<p>Hello</p>', 'ru' => '<p>Привет</p>']]);

        app()->setLocale('ru');
        expect($widget->render()->toHtml())->toBe('<p>Привет</p>');

        app()->setLocale('ky');
        expect($widget->render()->toHtml())->toBe('<p>Hello</p>');
    });

    it('purifies the HTML when it is saved', function () {
        $values = (new HtmlWidget)->validate(['content' => ['en' => '<p onclick="steal()">Hi</p><script>alert(1)</script>']]);

        expect($values['content']['en'])->toBe('<p>Hi</p>');
    });
});

describe('FormFields', function () {
    it('purifies editor fields by default, and on request for others', function () {
        $fields = FormFields::normalize([
            'body' => ['type' => 'editor'],
            'raw' => ['type' => 'textarea'],
            'clean' => ['type' => 'textarea', 'purify' => true],
            'trusted' => ['type' => 'editor', 'purify' => false],
        ], 'test');
        $dirty = '<b>ok</b><script>x()</script>';

        expect(FormFields::values($fields, ['body' => $dirty, 'raw' => $dirty, 'clean' => $dirty, 'trusted' => $dirty]))->toBe([
            'body' => '<b>ok</b>',
            'raw' => $dirty,
            'clean' => '<b>ok</b>',
            'trusted' => $dirty,
        ]);
    });

    it('resolves the defaults', function () {
        expect(FormFields::defaults(['a' => ['default' => 1], 'b' => ['default' => fn () => 'two'], 'c' => []]))
            ->toBe(['a' => 1, 'b' => 'two', 'c' => null]);
    });

    it('limits each language of a translatable field and can require the default language', function () {
        $fields = FormFields::normalize(['heading' => ['type' => 'translatable', 'label' => 'Heading', 'max' => 5, 'required' => true]], 'test');
        $passes = fn (array $input) => validator($input, FormFields::rules($fields), [], FormFields::attributes($fields))->passes();

        expect($passes(['heading' => ['en' => 'Short', 'ru' => 'Кратк']]))->toBeTrue()
            ->and($passes(['heading' => ['en' => 'Too long']]))->toBeFalse()
            ->and($passes(['heading' => ['ru' => 'Нет']]))->toBeFalse()
            ->and(FormFields::attributes($fields, 'settings.'))->toMatchArray(['settings.heading' => 'Heading', 'settings.heading.ru' => 'Heading (RU)']);
    });
});

it('registers the widgets listed in an enabled module\'s manifest', function () {
    $path = storage_path('framework/testing/widget-module-'.uniqid());
    config(['cms.modules_path' => $path]);
    app()->forgetInstance(ModuleManager::class);
    app()->forgetInstance(ModuleLoader::class);

    $slug = bootableModule($path, 'WidgetHost', ['widgets' => [GreetingWidget::class, 'Missing\\Widget']]);
    app(ModuleManager::class)->sync();
    Module::where('slug', $slug)->update(['installed_at' => now(), 'installed_version' => '1.0.0', 'enabled' => true]);
    app(ModuleManager::class)->flush();
    Log::spy();

    app(ModuleLoader::class)->load();

    expect(app(WidgetRegistry::class)->has('test.greeting'))->toBeTrue();
    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'Missing\\Widget'));

    File::deleteDirectory($path);
});
