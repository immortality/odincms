<?php

use App\Cms\Support\Facades\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Modules\Contacts\Models\Contact;
use Modules\Contacts\Models\ContactMessage;
use Modules\Contacts\Notifications\NewContactMessage;
use Modules\Navigation\Support\RouteOptions;

beforeEach(function () {
    config(['cms.locales' => ['en', 'ru'], 'cms.default_locale' => 'en']);

    $this->useModule('contacts');
});

describe('the contacts page', function () {
    it('shows the published contacts, a map of the first located one and the form', function () {
        Contact::factory()->published()->create([
            'title' => ['en' => 'Head office'], 'address' => ['en' => 'Manas 9'], 'city' => ['en' => 'Bishkek'],
            'phone' => '+996 555 000 111', 'email' => 'hello@example.com',
        ]);
        Contact::factory()->published()->located(40.5, 72.8)->create(['title' => ['en' => 'Osh branch']]);
        Contact::factory()->create(['title' => ['en' => 'Secret draft']]);

        $this->get('/contacts')->assertOk()
            ->assertSee(['Get in <span class="hl">touch</span>', 'Head office', 'Osh branch', 'Manas 9, Bishkek', 'Prefer e-mail?'], false)
            ->assertSee('href="tel:+996555000111"', false)
            ->assertSee('action="'.route('contacts.send').'"', false)
            ->assertSee('openstreetmap.org/export/embed.html', false)
            ->assertSee('marker=40.5%2C72.8', false)
            ->assertDontSee('Secret draft');
    });

    it('works without contacts and in other languages, with the heading from Settings', function () {
        $this->get('/contacts')->assertOk()->assertSee('Our contact details will appear here soon')->assertDontSee('<iframe', false);

        Settings::set('contacts.heading', ['en' => 'Say *hello*', 'ru' => 'Скажите *привет*']);
        $this->get('/ru/contacts')->assertOk()->assertSee('Скажите <span class="hl">привет</span>', false)->assertSee('Отправьте сообщение');
    });

    it('is a route link target for menus', function () {
        expect(app(RouteOptions::class)->has('contacts.index'))->toBeTrue();
    })->skip(fn () => ! class_exists(RouteOptions::class));
});

describe('sending the form', function () {
    it('stores the message, thanks the visitor and e-mails the team', function () {
        Notification::fake();
        Settings::set('contacts.notify_email', 'team@example.com');

        $response = $this->post('/ru/contacts', [
            'name' => 'Айбек', 'email' => 'aibek@example.com', 'phone' => '+996 555 123 456',
            'subject' => 'Сайт', 'message' => 'Нужен сайт для компании',
        ]);
        expect($response->headers->get('Location'))->toEndWith('/ru/contacts#contact-form');

        expect(ContactMessage::sole()->only(['name', 'email', 'phone', 'subject', 'message', 'locale', 'status']))->toBe([
            'name' => 'Айбек', 'email' => 'aibek@example.com', 'phone' => '+996 555 123 456',
            'subject' => 'Сайт', 'message' => 'Нужен сайт для компании', 'locale' => 'ru', 'status' => 'new',
        ]);
        Notification::assertSentOnDemand(NewContactMessage::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'team@example.com');

        $this->get('/ru/contacts')->assertSee('Спасибо! Ваше сообщение отправлено.')->assertDontSee('name="email"', false);
    });

    it('sends no e-mail without an address in Settings', function () {
        Notification::fake();

        $this->post(route('contacts.send'), ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hello, a question.'])->assertRedirect();

        expect(ContactMessage::sole()->phone)->toBeNull();
        Notification::assertNothingSent();
    });

    it('validates the form and shows the errors in the form', function () {
        $this->post(route('contacts.send'), ['name' => '', 'email' => 'nope', 'phone' => 'call me', 'message' => 'short'])
            ->assertRedirect(route('contacts.index').'#contact-form')
            ->assertSessionHasErrorsIn('contact', ['name', 'email', 'phone', 'message']);

        $this->post(route('contacts.send'), ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap things now', 'website' => 'http://spam'])
            ->assertSessionHasErrorsIn('contact', ['website']);

        expect(ContactMessage::count())->toBe(0);

        $this->post(route('contacts.send'), ['name' => 'Ann <b>', 'email' => 'wrong', 'message' => 'Hello there!']);
        $this->get('/contacts')->assertSee('is-invalid', false)->assertSee('value="Ann &lt;b&gt;"', false);
    });

    it('limits how often one address can send', function () {
        foreach (range(1, 5) as $i) {
            $this->post(route('contacts.send'), ['name' => 'Ann', 'email' => "ann{$i}@example.com", 'message' => 'Hello, a question.'])->assertRedirect();
        }

        $this->post(route('contacts.send'), ['name' => 'Ann', 'email' => 'ann6@example.com', 'message' => 'Hello, a question.'])->assertTooManyRequests();
    });
});

describe('the admin', function () {
    it('lists, searches, filters and shows messages to users with permission', function () {
        $new = ContactMessage::factory()->create(['name' => 'Aida', 'message' => 'Online store please']);
        ContactMessage::factory()->handled()->create(['name' => 'Bakyt']);

        $this->get(route('admin.contacts.messages.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.contacts.messages.index'))->assertForbidden();

        $this->actingAsUserWith(['contacts.view'])->get(route('admin.contacts.messages.index'))->assertOk()
            ->assertSee(['Aida', 'Bakyt', '2 messages in total, 1 new.']);
        $this->actingAsAdmin()->get(route('admin.contacts.messages.index', ['search' => 'store']))->assertSee('Aida')->assertDontSee('Bakyt');
        $this->actingAsAdmin()->get(route('admin.contacts.messages.index', ['status' => 'handled']))->assertSee('Bakyt')->assertDontSee('Aida');
        $this->actingAsUserWith(['contacts.view'])->get(route('admin.contacts.messages.show', $new))->assertOk()->assertSee('Online store please');
    });

    it('marks messages handled, deletes them and runs bulk actions with the right permissions', function () {
        [$a, $b] = ContactMessage::factory()->count(2)->create();

        $this->actingAsUserWith(['contacts.view'])->patch(route('admin.contacts.messages.status', $a), ['status' => 'handled'])->assertForbidden();
        $this->actingAsUserWith(['contacts.view', 'contacts.edit'])->patch(route('admin.contacts.messages.status', $a), ['status' => 'handled'])->assertRedirect();
        expect($a->fresh()->status)->toBe('handled');

        $this->actingAsUserWith(['contacts.view', 'contacts.edit'])->delete(route('admin.contacts.messages.destroy', $a))->assertForbidden();
        $this->actingAsAdmin()->post(route('admin.contacts.messages.bulk'), ['action' => 'handled', 'ids' => [$b->id]])->assertRedirect();
        expect($b->fresh()->status)->toBe('handled');

        $this->actingAsAdmin()->delete(route('admin.contacts.messages.destroy', $a))->assertRedirect(route('admin.contacts.messages.index'));
        expect(ContactMessage::count())->toBe(1);
    });

    it('is in the admin menu', function () {
        $this->actingAsAdmin()->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.contacts.messages.index'), false);
    });
});
