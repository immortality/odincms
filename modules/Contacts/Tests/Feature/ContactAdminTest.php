<?php

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Contacts\Models\Contact;

beforeEach(function () {
    config(['cms.locales' => ['en', 'ru', 'ky'], 'cms.default_locale' => 'en']);

    $this->useModule('contacts');
});

it('is an optional module: it can be disabled and uninstalled', function () {
    $module = Module::where('slug', 'contacts')->sole();

    expect($module->is_core)->toBeFalse()
        ->and(app(ModuleManager::class)->allowedActions($module))->toContain('disable', 'uninstall');
});

it('sends guests to the login page and forbids users without permission', function () {
    $this->get(route('admin.contacts.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.contacts.index'))->assertForbidden();
    $this->actingAsUserWith(['contacts.view'])->get(route('admin.contacts.index'))->assertOk();
    $this->actingAsUserWith(['contacts.view'])->get(route('admin.contacts.create'))->assertForbidden();
});

it('lists, searches and filters contacts', function () {
    Contact::factory()->published()->located()->create(['title' => ['en' => 'Head office'], 'city' => ['en' => 'Bishkek', 'ru' => 'Бишкек']]);
    Contact::factory()->create(['title' => ['en' => 'Osh branch'], 'city' => ['en' => 'Osh'], 'phone' => '+996 3222 55 66']);

    $this->actingAsAdmin()->get(route('admin.contacts.index'))->assertOk()
        ->assertSee(['Head office', 'Osh branch'])
        ->assertSee('openstreetmap.org/?mlat=42.8746', false); // the "open in map" link of the located one

    $this->actingAsAdmin()->get(route('admin.contacts.index', ['search' => 'бишкек']))
        ->assertSee('Head office')->assertDontSee('Osh branch');
    $this->actingAsAdmin()->get(route('admin.contacts.index', ['search' => '3222']))
        ->assertSee('Osh branch')->assertDontSee('Head office');
    $this->actingAsAdmin()->get(route('admin.contacts.index', ['status' => 'draft']))
        ->assertSee('Osh branch')->assertDontSee('Head office');
});

it('creates a contact with translations, coordinates and a photo', function () {
    Storage::fake('public');

    $this->actingAsAdmin()->get(route('admin.contacts.create'))->assertOk()
        ->assertSee('data-map-picker', false)->assertSee('name="latitude"', false)->assertSee('name="longitude"', false);

    $this->actingAsAdmin()->post(route('admin.contacts.store'), [
        'title' => ['en' => 'Head office', 'ru' => 'Главный офис', 'ky' => ''],
        'address' => ['en' => '1 Chui Avenue', 'ru' => 'пр. Чуй, 1'],
        'city' => ['en' => 'Bishkek'],
        'phone' => '+996 (312) 66-00-11',
        'email' => 'office@example.com',
        'latitude' => '42,87461234',   // a decimal comma is accepted
        'longitude' => '74.5698',
        'slug' => '',
        'status' => 'published',
        'photo' => UploadedFile::fake()->image('office.jpg', 1600, 900),
    ])->assertRedirect()->assertSessionHas('success');

    $contact = Contact::sole();
    expect($contact->slug)->toBe('head-office')
        ->and($contact->getTranslations('title'))->toBe(['en' => 'Head office', 'ru' => 'Главный офис'])
        ->and($contact->getTranslations('address'))->toBe(['en' => '1 Chui Avenue', 'ru' => 'пр. Чуй, 1'])
        ->and($contact->phone)->toBe('+996 (312) 66-00-11')
        ->and($contact->latitude)->toBe(42.8746123)
        ->and($contact->longitude)->toBe(74.5698)
        ->and($contact->isPublished())->toBeTrue()
        ->and($contact->photoUrl())->toContain('conversions');
});

it('validates the fields and needs both coordinates or none', function () {
    Contact::factory()->create(['slug' => 'taken']);

    $this->actingAsAdmin()->post(route('admin.contacts.store'), [
        'title' => ['en' => ''],
        'slug' => 'Taken',
        'phone' => 'call me',
        'email' => 'not-an-email',
        'latitude' => '95',
        'status' => 'archived',
        'photo' => UploadedFile::fake()->create('virus.exe', 10),
    ])->assertSessionHasErrors(['title.en', 'slug', 'phone', 'email', 'latitude', 'longitude', 'status', 'photo']);

    $this->actingAsAdmin()->post(route('admin.contacts.store'), [
        'title' => ['en' => 'Somewhere'], 'status' => 'draft', 'latitude' => '', 'longitude' => '74.5',
    ])->assertSessionHasErrors(['latitude'])->assertSessionDoesntHaveErrors(['longitude']);

    expect(Contact::count())->toBe(1);
});

it('updates a contact, clears its point and removes its photo on request', function () {
    Storage::fake('public');
    $contact = Contact::factory()->located()->create();
    $contact->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('photo');

    // Save (in the page head) submits the form through its id; Delete has its own form there.
    $this->actingAsAdmin()->get(route('admin.contacts.edit', $contact))->assertOk()
        ->assertSee('form="contact-form"', false)->assertSee('id="contact-form"', false)
        ->assertSee('value="42.8746"', false)
        ->assertSee('value="DELETE"', false);

    $this->actingAsAdmin()->put(route('admin.contacts.update', $contact), [
        'title' => ['en' => 'Renamed'], 'slug' => 'new-name', 'status' => 'draft',
        'latitude' => '', 'longitude' => '', 'photo_remove' => '1',
    ])->assertRedirect(route('admin.contacts.edit', $contact));

    $contact->refresh();
    expect($contact->title)->toBe('Renamed')
        ->and($contact->slug)->toBe('new-name')
        ->and($contact->hasCoordinates())->toBeFalse()
        ->and($contact->mapUrl())->toBeNull()
        ->and($contact->photoUrl())->toBeNull();
});

it('deletes a contact', function () {
    $contact = Contact::factory()->create();

    $this->actingAsAdmin()->delete(route('admin.contacts.destroy', $contact))->assertRedirect(route('admin.contacts.index'));

    expect(Contact::count())->toBe(0);
});

it('publishes in bulk, and needs a permission for it', function () {
    [$a, $b] = Contact::factory()->count(2)->create();

    $this->actingAsAdmin()->post(route('admin.contacts.bulk'), ['action' => 'publish', 'ids' => [$a->id, $b->id]])
        ->assertSessionHas('success');
    expect(Contact::published()->count())->toBe(2);

    $this->actingAsUserWith(['contacts.view'])->post(route('admin.contacts.bulk'), ['action' => 'delete', 'ids' => [$a->id]])
        ->assertForbidden();
});
