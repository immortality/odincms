<?php

use App\Cms\Support\LocalizedUrls;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['cms.locales' => ['en', 'ru', 'ky'], 'cms.default_locale' => 'en', 'app.url' => 'http://localhost']);
    URL::useOrigin('http://localhost');

    Route::middleware('web')->group(function () {
        Route::get('/hello', fn () => app()->getLocale().'|'.route('test.hello').'|'.request('x'))->name('test.hello');
        Route::get('/hello/pages', fn () => Paginator::resolveCurrentPath())->name('test.pages');
        Route::post('/echo', fn (Request $request) => $request->input('a'))->name('test.echo');
    });
    Route::getRoutes()->refreshNameLookups();
});

it('routes /{locale}/… for the other locales and keeps the prefix in generated URLs', function () {
    $this->get('/ru/hello?x=1')->assertOk()->assertContent('ru|http://localhost/ru/hello|1');
    $this->get('/ky/hello')->assertOk()->assertContent('ky|http://localhost/ky/hello|');
});

it('serves the default locale without a prefix and redirects its prefixed URLs', function () {
    $this->get('/hello')->assertOk()->assertContent('en|http://localhost/hello|');
    $this->get('/en/hello?x=1')->assertRedirect('http://localhost/hello?x=1')->assertStatus(301);
});

it('falls back to the remembered or the user\'s locale without a prefix', function () {
    $this->actingAs(User::factory()->create(['locale' => 'ky']))
        ->get('/hello')->assertContent('ky|http://localhost/ky/hello|');
});

it('ignores inactive locales and never prefixes the admin panel', function () {
    config(['cms.locales' => ['en', 'ru']]);

    $this->get('/ky/hello')->assertNotFound();
    $this->get('/ru/admin')->assertNotFound();

    app()->setLocale('ru');
    expect(route('admin.dashboard'))->toBe('http://localhost/admin')
        ->and(url('/storage/a.jpg'))->toBe('http://localhost/storage/a.jpg')
        ->and(url('/'))->toBe('http://localhost/ru')
        ->and(route('test.hello'))->toBe('http://localhost/ru/hello');
});

it('keeps posted data when it removes the prefix', function () {
    $this->post('/ru/echo', ['a' => 'kept'])->assertContent('kept');
});

it('keeps the prefix in pagination links', function () {
    $this->get('/ru/hello/pages')->assertContent('http://localhost/ru/hello/pages');
});

it('links the current page in each locale', function () {
    $request = Request::create('http://localhost/blog/news?page=2&tag=x');

    expect(LocalizedUrls::current('ru', $request))->toBe('http://localhost/ru/blog/news?tag=x')
        ->and(LocalizedUrls::current('en', $request))->toBe('http://localhost/blog/news?tag=x&locale=en')
        ->and(LocalizedUrls::alternates($request))->toBe([
            'en' => 'http://localhost/blog/news',
            'ru' => 'http://localhost/ru/blog/news',
            'ky' => 'http://localhost/ky/blog/news',
        ]);
});
