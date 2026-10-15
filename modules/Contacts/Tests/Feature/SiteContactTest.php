<?php

use Illuminate\Support\Facades\DB;
use Modules\Contacts\Models\Contact;

beforeEach(function () {
    config(['cms.locales' => ['en', 'ru'], 'cms.default_locale' => 'en']);

    $this->useModule('contacts');
});

it('gives the ways to reach a contact in the order asked, without empty ones', function () {
    $contact = Contact::factory()->located()->make([
        'address' => ['en' => 'Manas 9'], 'city' => ['en' => 'Bishkek'],
        'phone' => '+996 (312) 66-00-11', 'email' => 'office@example.com',
    ]);

    expect($contact->telUrl())->toBe('tel:+996312660011')
        ->and($contact->fullAddress())->toBe('Manas 9, Bishkek')
        ->and($contact->links(['phone', 'email']))->toBe([
            ['type' => 'phone', 'text' => '+996 (312) 66-00-11', 'url' => 'tel:+996312660011'],
            ['type' => 'email', 'text' => 'office@example.com', 'url' => 'mailto:office@example.com'],
        ])
        ->and($contact->links(['address'])[0]['url'])->toContain('openstreetmap.org');

    $bare = Contact::factory()->make(['address' => null, 'city' => ['en' => 'Osh'], 'phone' => null, 'email' => null]);
    expect($bare->links())->toBe([['type' => 'address', 'text' => 'Osh', 'url' => null]])
        ->and($bare->telUrl())->toBeNull();
});

it('caches the site contact (the first published one) until a contact changes', function () {
    expect(Contact::primary())->toBeNull();

    Contact::factory()->create(['title' => ['en' => 'Draft office']]);
    $first = Contact::factory()->published()->create(['title' => ['en' => 'Head office'], 'city' => ['en' => 'Bishkek', 'ru' => 'Бишкек']]);
    Contact::factory()->published()->create(['title' => ['en' => 'Branch']]);

    expect(Contact::primary()?->is($first))->toBeTrue();

    DB::enableQueryLog();
    $cached = Contact::primary();
    expect(DB::getQueryLog())->toBe([])
        ->and($cached->title)->toBe('Head office')
        ->and($cached->getTranslations('city'))->toBe(['en' => 'Bishkek', 'ru' => 'Бишкек']);

    $first->update(['status' => Contact::DRAFT]);
    expect(Contact::primary()?->title)->toBe('Branch');
});

it('shows the site contact in the theme\'s top bar, mobile menu and footer', function () {
    Contact::factory()->published()->create([
        'address' => ['en' => 'Manas 9'], 'city' => ['en' => 'Bishkek'], 'phone' => '+996 555 000 111', 'email' => 'hello@example.com',
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, 'href="mailto:hello@example.com"'))->toBe(3)
        ->and(substr_count($html, 'href="tel:+996555000111"'))->toBe(3)
        ->and(substr_count($html, 'Manas 9, Bishkek'))->toBe(2) // not in the mobile menu
        ->and($html)->toContain('<div class="topbar');
});

it('leaves the contact parts out without a published contact', function () {
    $this->get('/')->assertOk()->assertDontSee('<div class="topbar', false)->assertDontSee('mailto:', false);
});
