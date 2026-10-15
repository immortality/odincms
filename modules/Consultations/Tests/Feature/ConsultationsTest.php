<?php

use App\Cms\Support\Facades\Settings;
use App\Cms\Widgets\WidgetRegistry;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Modules\Consultations\Database\Seeders\ConsultationsDatabaseSeeder;
use Modules\Consultations\Models\ConsultationRequest;
use Modules\Consultations\Notifications\NewConsultationRequest;
use Modules\Consultations\Widgets\ConsultationFormWidget;
use Modules\Services\Models\Service;
use Modules\Widgets\Database\Seeders\WidgetsDatabaseSeeder;
use Modules\Widgets\Models\WidgetArea;
use Modules\Widgets\Models\WidgetInstance;

beforeEach(function () {
    foreach (['services', 'widgets', 'consultations'] as $module) {
        $this->useModule($module);
    }
});

/**
 * The form widget placed in the home area.
 */
function placeConsultationForm(array $settings = []): WidgetInstance
{
    test()->seed(WidgetsDatabaseSeeder::class);

    return WidgetInstance::create([
        'area_id' => WidgetArea::where('slug', 'home')->sole()->id,
        'widget' => 'consultations.form',
        'settings' => ['slogan' => ['en' => 'Let us build it', 'ru' => 'Давайте построим'], 'stat_value' => '40+', ...$settings],
        'sort_order' => 1, 'is_active' => true, 'cache_ttl' => 3600,
    ]);
}

describe('the widget', function () {
    it('is registered and lists the published services', function () {
        Service::factory()->published()->create(['title' => ['en' => 'Web Development']]);
        Service::factory()->create(['title' => ['en' => 'Secret draft']]);

        expect(app(WidgetRegistry::class)->get('consultations.form'))->toBe(ConsultationFormWidget::class);

        $html = (new ConsultationFormWidget(['slogan' => ['en' => 'Let us <b>build</b>'], 'stat_value' => '40+']))->render()->render();

        expect($html)->toContain('id="consultation"', 'action="'.route('consultations.store').'"', 'name="_token"',
            'Request a free consultation', '40+', 'Let us &lt;b&gt;build&lt;/b&gt;', 'Web Development')
            ->not->toContain('Secret draft');
    });

    it('is never cached, so each visitor gets their own CSRF token', function () {
        placeConsultationForm();

        $this->get('/')->assertOk()->assertSee('Let us build it');
        session()->regenerateToken();

        $this->get('/')->assertSee('value="'.csrf_token().'"', false);
    });
});

describe('sending the form', function () {
    it('stores the request with the service title in the visitor\'s language, thanks the visitor and e-mails the team', function () {
        Notification::fake();
        Settings::set('consultations.notify_email', 'team@example.com');
        $service = Service::factory()->published()->create(['title' => ['en' => 'Web Development', 'ru' => 'Веб-разработка']]);
        placeConsultationForm();

        $this->from('/ru')->post('/ru/consultation-requests', [
            'name' => 'Айбек', 'email' => 'aibek@example.com', 'service' => (string) $service->id, 'message' => 'Нужен сайт',
        ])->assertRedirect(url('/ru').'#consultation');

        $request = ConsultationRequest::sole();
        expect($request->only(['name', 'email', 'service', 'message', 'locale', 'status']))->toBe([
            'name' => 'Айбек', 'email' => 'aibek@example.com', 'service' => 'Веб-разработка',
            'message' => 'Нужен сайт', 'locale' => 'ru', 'status' => 'new',
        ]);
        Notification::assertSentOnDemand(NewConsultationRequest::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'team@example.com');

        $this->get('/ru')->assertSee('Спасибо! Ваша заявка отправлена.')->assertDontSee('name="email"', false);
    });

    it('sends no e-mail without an address in Settings', function () {
        Notification::fake();

        $this->post(route('consultations.store'), ['name' => 'Ann', 'email' => 'ann@example.com'])->assertRedirect();

        expect(ConsultationRequest::count())->toBe(1);
        Notification::assertNothingSent();
    });

    it('validates the form and shows the errors in the form', function () {
        Service::factory()->create(['title' => ['en' => 'Draft service']]);
        $draft = Service::sole();
        placeConsultationForm();

        $this->from('/')->post(route('consultations.store'), ['name' => '', 'email' => 'not-an-email', 'service' => (string) $draft->id])
            ->assertRedirect(url('/').'#consultation')
            ->assertSessionHasErrorsIn('consultation', ['name', 'email', 'service']);

        $this->from('/')->post(route('consultations.store'), ['name' => 'Bot', 'email' => 'bot@example.com', 'website' => 'http://spam'])
            ->assertSessionHasErrorsIn('consultation', ['website']);

        expect(ConsultationRequest::count())->toBe(0);

        $this->from('/')->post(route('consultations.store'), ['name' => 'Ann <b>', 'email' => 'wrong']);
        $this->get('/')->assertSee('is-invalid', false)->assertSee('value="Ann &lt;b&gt;"', false);
    });

    it('limits how often one address can send', function () {
        foreach (range(1, 5) as $i) {
            $this->post(route('consultations.store'), ['name' => 'Ann', 'email' => "ann{$i}@example.com"])->assertRedirect();
        }

        $this->post(route('consultations.store'), ['name' => 'Ann', 'email' => 'ann6@example.com'])->assertTooManyRequests();
    });
});

describe('the admin', function () {
    it('lists, searches, filters and shows requests to users with permission', function () {
        $new = ConsultationRequest::factory()->create(['name' => 'Aida', 'message' => 'Online store please']);
        ConsultationRequest::factory()->handled()->create(['name' => 'Bakyt']);

        $this->get(route('admin.consultations.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.consultations.index'))->assertForbidden();

        $this->actingAsUserWith(['consultations.view'])->get(route('admin.consultations.index'))->assertOk()
            ->assertSee(['Aida', 'Bakyt', '2 requests in total, 1 new.']);
        $this->actingAsAdmin()->get(route('admin.consultations.index', ['search' => 'store']))->assertSee('Aida')->assertDontSee('Bakyt');
        $this->actingAsAdmin()->get(route('admin.consultations.index', ['status' => 'handled']))->assertSee('Bakyt')->assertDontSee('Aida');
        $this->actingAsUserWith(['consultations.view'])->get(route('admin.consultations.show', $new))->assertOk()->assertSee('Online store please');
    });

    it('marks requests handled, deletes them and runs bulk actions with the right permissions', function () {
        [$a, $b] = ConsultationRequest::factory()->count(2)->create();

        $this->actingAsUserWith(['consultations.view'])->patch(route('admin.consultations.status', $a), ['status' => 'handled'])->assertForbidden();
        $this->actingAsUserWith(['consultations.view', 'consultations.edit'])->patch(route('admin.consultations.status', $a), ['status' => 'handled'])->assertRedirect();
        expect($a->fresh()->status)->toBe('handled');

        $this->actingAsUserWith(['consultations.view', 'consultations.edit'])->delete(route('admin.consultations.destroy', $a))->assertForbidden();
        $this->actingAsAdmin()->post(route('admin.consultations.bulk'), ['action' => 'handled', 'ids' => [$b->id]])->assertRedirect();
        expect($b->fresh()->status)->toBe('handled');

        $this->actingAsAdmin()->delete(route('admin.consultations.destroy', $a))->assertRedirect(route('admin.consultations.index'));
        expect(ConsultationRequest::count())->toBe(1);
    });

    it('is in the Content group of the admin menu', function () {
        $this->actingAsAdmin()->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.consultations.index'), false);
    });
});

describe('the seeder', function () {
    it('appends the form to the home area once, and does nothing without the area', function () {
        $this->seed(ConsultationsDatabaseSeeder::class);
        expect(WidgetInstance::count())->toBe(0);

        $this->seed(WidgetsDatabaseSeeder::class);
        $this->seed(ConsultationsDatabaseSeeder::class);
        $this->seed(ConsultationsDatabaseSeeder::class);

        $instance = WidgetInstance::where('widget', 'consultations.form')->sole();
        expect($instance->area->slug)->toBe('home')
            ->and((new ConsultationFormWidget)->validate($instance->settings))->toBeArray();
    });
});
