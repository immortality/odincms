<?php

use App\Cms\Settings\Settings;
use App\Cms\Support\Facades\Settings as SettingsFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->settings = new Settings(app('cache.store'));
    $this->settings->flush();
});

describe('values', function () {
    it('stores and reads values of any JSON type', function () {
        $this->settings->set('blog.per_page', 20);
        $this->settings->set('blog.enabled', true);
        $this->settings->set('blog.tags', ['news', 'events']);
        $this->settings->set('site.name', ['en' => 'OdinCMS', 'ru' => 'ОдинЦМС']);

        $fresh = new Settings(app('cache.store'));
        $fresh->flush();

        expect($fresh->get('blog.per_page'))->toBe(20)
            ->and($fresh->get('blog.enabled'))->toBeTrue()
            ->and($fresh->get('blog.tags'))->toBe(['news', 'events'])
            ->and($fresh->get('site.name'))->toBe(['en' => 'OdinCMS', 'ru' => 'ОдинЦМС']);
    });

    it('records the section and the owning module', function () {
        $this->settings->section('blog', 'Blog', ['per_page' => ['type' => 'number', 'label' => 'Per page']], module: 'blog');
        $this->settings->set('blog.per_page', 5);
        $this->settings->set('misc.flag', true);

        expect(DB::table('settings')->where('key', 'blog.per_page')->first())
            ->group->toBe('blog')
            ->module->toBe('blog')
            ->and(DB::table('settings')->where('key', 'misc.flag')->value('module'))->toBeNull();
    });

    it('overwrites a value', function () {
        $this->settings->set('site.name', 'One');
        $this->settings->set('site.name', 'Two');

        expect($this->settings->get('site.name'))->toBe('Two')
            ->and(DB::table('settings')->count())->toBe(1);
    });

    it('falls back to the registered default, then to the given default', function () {
        $this->settings->section('blog', 'Blog', [
            'per_page' => ['type' => 'number', 'label' => 'Per page', 'default' => 10],
            'sort' => ['type' => 'select', 'label' => 'Sort', 'options' => ['new' => 'Newest'], 'default' => fn () => 'new'],
        ]);

        expect($this->settings->get('blog.per_page'))->toBe(10)
            ->and($this->settings->get('blog.sort'))->toBe('new')
            ->and($this->settings->get('blog.unknown', 'fallback'))->toBe('fallback');
    });

    it('translates a translatable value with a fallback to the default locale', function () {
        $this->settings->set('site.name', ['en' => 'My site', 'ru' => 'Мой сайт']);

        expect($this->settings->translated('site.name', 'ru'))->toBe('Мой сайт')
            ->and($this->settings->translated('site.name', 'ky'))->toBe('My site')
            ->and($this->settings->translated('missing.key', 'en', 'Default'))->toBe('Default');
    });

    it('forgets a value', function () {
        $this->settings->set('site.name', 'X');
        $this->settings->forget('site.name');

        expect($this->settings->get('site.name'))->toBeNull();
    });

    it('caches all values until a value is changed', function () {
        $this->settings->set('site.name', 'Cached');
        $this->settings->all();

        DB::table('settings')->update(['value' => json_encode('Changed behind its back')]);
        expect((new Settings(app('cache.store')))->get('site.name'))->toBe('Cached');

        $this->settings->set('other.key', 1); // any change clears the cache
        expect((new Settings(app('cache.store')))->get('site.name'))->toBe('Changed behind its back');
    });

    it('returns nothing before the settings table exists', function () {
        Schema::drop('settings');

        expect($this->settings->all())->toBe([])
            ->and($this->settings->get('site.name', 'default'))->toBe('default');
    });

    it('is available through the facade', function () {
        SettingsFacade::set('site.name', 'Via facade');

        expect(SettingsFacade::get('site.name'))->toBe('Via facade');
    });
});

describe('sections', function () {
    it('lists the sections by order', function () {
        $this->settings->section('maintenance', 'Maintenance', [], order: 90);
        $this->settings->section('site', 'Site', [], order: 10);
        $this->settings->section('blog', 'Blog', []);

        expect(array_keys($this->settings->sections()))->toBe(['site', 'blog', 'maintenance']);
    });

    it('rejects unknown field types', function () {
        $this->settings->section('blog', 'Blog', ['x' => ['type' => 'colour', 'label' => 'X']]);
    })->throws(InvalidArgumentException::class, 'unknown type "colour"');

    it('builds validation rules from the field types', function () {
        $this->settings->section('demo', 'Demo', [
            'email' => ['type' => 'email', 'label' => 'E'],
            'count' => ['type' => 'number', 'label' => 'C', 'rules' => ['integer', 'min:1']],
            'on' => ['type' => 'checkbox', 'label' => 'O'],
            'one' => ['type' => 'select', 'label' => 'S', 'options' => ['a' => 'A', 'b' => 'B']],
            'many' => ['type' => 'multiselect', 'label' => 'M', 'options' => ['a' => 'A', 'b' => 'B']],
            'title' => ['type' => 'translatable', 'label' => 'T'],
        ]);

        $validate = fn (array $input) => validator($input, $this->settings->rules('demo'))->errors()->keys();

        expect($validate(['email' => 'x', 'count' => 0, 'on' => 'maybe', 'one' => 'c', 'many' => ['a', 'z'], 'title' => 'not an array']))
            ->toEqualCanonicalizing(['email', 'count', 'on', 'one', 'many.1', 'title'])
            ->and($validate(['email' => 'a@b.cd', 'count' => 3, 'on' => '1', 'one' => 'a', 'many' => ['a', 'b'], 'title' => ['en' => 'Hi']]))
            ->toBe([]);
    });

    it('converts form input to stored values', function () {
        $this->settings->section('demo', 'Demo', [
            'on' => ['type' => 'checkbox', 'label' => 'O'],
            'count' => ['type' => 'number', 'label' => 'C'],
            'many' => ['type' => 'multiselect', 'label' => 'M', 'options' => ['a' => 'A']],
            'title' => ['type' => 'translatable', 'label' => 'T'],
            'text' => ['type' => 'text', 'label' => 'X'],
        ]);

        expect($this->settings->valuesFromInput('demo', ['on' => '0', 'count' => '42', 'title' => ['en' => 'Hi', 'ru' => ''], 'text' => 'abc']))
            ->toBe([
                'demo.on' => false,
                'demo.count' => 42,
                'demo.many' => [],
                'demo.title' => ['en' => 'Hi'],
                'demo.text' => 'abc',
            ]);
    });
});
