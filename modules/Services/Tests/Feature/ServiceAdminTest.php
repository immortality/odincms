<?php

use App\Cms\Support\LinkableRegistry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Services\Models\Service;

beforeEach(function () {
    config(['cms.locales' => ['en', 'ru', 'ky'], 'cms.default_locale' => 'en', 'app.url' => 'http://localhost']);
    URL::useOrigin('http://localhost');

    $this->useModule('services');
});

describe('admin', function () {
    it('sends guests to the login page and forbids users without permission', function () {
        $this->get(route('admin.services.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.services.index'))->assertForbidden();
        $this->actingAsUserWith(['services.view'])->get(route('admin.services.index'))->assertOk();
        $this->actingAsUserWith(['services.view'])->get(route('admin.services.create'))->assertForbidden();
    });

    it('lists, searches and filters services', function () {
        Service::factory()->published()->create(['title' => ['en' => 'Guided tours', 'ru' => 'Экскурсии']]);
        Service::factory()->create(['title' => ['en' => 'Car rental']]);

        $this->actingAsAdmin()->get(route('admin.services.index'))->assertOk()->assertSee(['Guided tours', 'Car rental']);
        $this->actingAsAdmin()->get(route('admin.services.index', ['search' => 'экскурс']))
            ->assertSee('Guided tours')->assertDontSee('Car rental');
        $this->actingAsAdmin()->get(route('admin.services.index', ['status' => 'draft']))
            ->assertSee('Car rental')->assertDontSee('Guided tours');
    });

    it('creates a service with translations, a slug, a photo and a purified detail', function () {
        Storage::fake('public');

        $this->actingAsAdmin()->get(route('admin.services.create'))->assertOk();

        $this->actingAsAdmin()->post(route('admin.services.store'), [
            'title' => ['en' => 'Horse riding', 'ru' => 'Конные прогулки', 'ky' => ''],
            'detail' => ['en' => '<p onclick="x()">Two hours</p><script>alert(1)</script>'],
            'meta_title' => ['en' => 'Horse riding in Kyrgyzstan'],
            'meta_description' => ['en' => ''],
            'slug' => '',
            'status' => 'published',
            'photo' => UploadedFile::fake()->image('horse.jpg', 1600, 900),
        ])->assertRedirect()->assertSessionHas('success');

        $service = Service::sole();
        expect($service->slug)->toBe('horse-riding')
            ->and($service->getTranslations('title'))->toBe(['en' => 'Horse riding', 'ru' => 'Конные прогулки'])
            ->and($service->getTranslation('detail', 'en'))->toBe('<p>Two hours</p>')
            ->and($service->getTranslations('meta_description'))->toBe([])
            ->and($service->isPublished())->toBeTrue()
            ->and($service->photoUrl())->toContain('conversions');
    });

    it('validates the title, the slug, the status and the photo', function () {
        Service::factory()->create(['slug' => 'taken']);

        $this->actingAsAdmin()->post(route('admin.services.store'), [
            'title' => ['en' => ''],
            'slug' => 'Taken',
            'status' => 'archived',
            'photo' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertSessionHasErrors(['title.en', 'slug', 'status', 'photo']);

        expect(Service::count())->toBe(1);
    });

    it('updates a service and removes its photo on request', function () {
        Storage::fake('public');
        $service = Service::factory()->create();
        $service->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('photo');

        // Save (in the page head) submits the form through its id; Delete has its own form there.
        $this->actingAsAdmin()->get(route('admin.services.edit', $service))->assertOk()
            ->assertSee('form="service-form"', false)->assertSee('id="service-form"', false)
            ->assertSee('value="DELETE"', false); // the delete form's method field

        $this->actingAsAdmin()->put(route('admin.services.update', $service), [
            'title' => ['en' => 'Renamed'], 'slug' => 'new-address', 'status' => 'draft', 'photo_remove' => '1',
        ])->assertRedirect(route('admin.services.edit', $service));

        expect($service->fresh())->title->toBe('Renamed')->slug->toBe('new-address')
            ->and($service->fresh()->photoUrl())->toBeNull();
    });

    it('deletes a service', function () {
        $service = Service::factory()->create();

        $this->actingAsAdmin()->delete(route('admin.services.destroy', $service))->assertRedirect(route('admin.services.index'));

        expect(Service::count())->toBe(0);
    });

    it('publishes in bulk, and needs a permission for it', function () {
        [$a, $b] = Service::factory()->count(2)->create();

        $this->actingAsAdmin()->post(route('admin.services.bulk'), ['action' => 'publish', 'ids' => [$a->id, $b->id]])
            ->assertSessionHas('success');
        expect(Service::published()->count())->toBe(2);

        $this->actingAsUserWith(['services.view'])->post(route('admin.services.bulk'), ['action' => 'delete', 'ids' => [$a->id]])
            ->assertForbidden();
    });
});

describe('frontend', function () {
    it('lists published services only', function () {
        Service::factory()->published()->create(['title' => ['en' => 'Guided tours']]);
        Service::factory()->create(['title' => ['en' => 'Secret draft']]);

        $this->get('/services')->assertOk()->assertSee('Guided tours')->assertDontSee('Secret draft');
    });

    it('shows a service with SEO tags in each language, and 404s for drafts', function () {
        $service = Service::factory()->published()->create([
            'title' => ['en' => 'Horse riding', 'ru' => 'Конные прогулки'],
            'detail' => ['en' => '<p>Two hours in the mountains.</p>'],
            'meta_description' => ['en' => 'Ride & relax'],
        ]);

        $this->get('/services/'.$service->slug)
            ->assertOk()
            ->assertSee('<title>Horse riding | ', false)
            ->assertSee('<meta name="description" content="Ride &amp; relax">', false)
            ->assertSee('<p>Two hours in the mountains.</p>', false);

        $this->get('/ru/services/'.$service->slug)->assertOk()->assertSee('Конные прогулки');

        $this->get('/services/'.Service::factory()->create()->slug)->assertNotFound();
        $this->get('/services/no-such-service')->assertNotFound();
    });

    it('previews drafts for those who may edit them', function () {
        $draft = Service::factory()->create(['title' => ['en' => 'Coming soon']]);

        $this->actingAsAdmin()->get('/services/'.$draft->slug)->assertOk()->assertSee(__('services::front.draft'));
    });

    it('offers services to navigation links', function () {
        $service = Service::factory()->published()->create(['title' => ['en' => 'Transfers'], 'slug' => 'transfers']);

        expect(app(LinkableRegistry::class)->has(Service::class))->toBeTrue()
            ->and(Service::linkableOptions())->toBe([$service->id => 'Transfers'])
            ->and($service->linkUrl())->toBe('http://localhost/services/transfers');
    });
});
