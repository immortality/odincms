<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;

describe('routes', function () {
    it('serves the auth pages under the admin prefix', function () {
        expect(route('login', absolute: false))->toBe('/admin/login')
            ->and(route('password.request', absolute: false))->toBe('/admin/forgot-password')
            ->and(route('two-factor.login', absolute: false))->toBe('/admin/two-factor-challenge');
    });

    it('has no public registration and no passkeys', function () {
        $this->get('/admin/register')->assertNotFound();
        $this->post('/admin/register')->assertNotFound();

        expect(collect(app('router')->getRoutes())->filter(fn ($route) => str_contains($route->uri(), 'passkey')))->toBeEmpty();
    });
});

describe('login', function () {
    it('shows the login page', function () {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('data-auth-form', false)
            ->assertSee('name="email"', false)
            ->assertSee(route('password.request'), false);
    });

    it('signs in an active user, records the login time and goes to the dashboard', function () {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
        expect($user->fresh()->last_login_at)->not->toBeNull();
    });

    it('accepts the email in any case', function () {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->post('/admin/login', ['email' => 'Admin@Example.COM', 'password' => 'password']);

        $this->assertAuthenticated();
    });

    it('rejects a wrong password and logs the attempt', function () {
        Log::spy();
        User::factory()->create(['email' => 'admin@example.com']);

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $message === 'Failed login attempt.'
            && $context['email'] === 'admin@example.com');
    });

    it('rejects a deactivated user, even with the right password', function () {
        Log::spy();
        $user = User::factory()->inactive()->create(['email' => 'old@example.com']);

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'old@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account has been deactivated.']);

        $this->assertGuest();
        expect($user->fresh()->last_login_at)->toBeNull();
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'Failed login attempt.');
    });

    it('throttles repeated attempts and logs the lockout', function () {
        $this->freezeTime(); // the message counts down the seconds
        Log::spy();
        User::factory()->create(['email' => 'admin@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong']);
        }

        // Even the right password is refused now, with the error shown on the form.
        $this->from('/admin/login')
            ->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again in 60 seconds.']);

        $this->assertGuest();
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'locked out'));
    });

    it('signs out to the login page', function () {
        $this->actingAs(User::factory()->create())
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');

        $this->assertGuest();
    });
});

describe('two-factor authentication', function () {
    function userWithTwoFactor(): array
    {
        $secret = app(Google2FA::class)->generateSecretKey();
        $user = User::factory()->create(['email' => '2fa@example.com']);
        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1', 'recovery-code-2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return [$user, $secret];
    }

    it('asks for a code after the password, before signing in', function () {
        [$user] = userWithTwoFactor();

        $this->post('/admin/login', ['email' => '2fa@example.com', 'password' => 'password'])
            ->assertRedirect('/admin/two-factor-challenge');

        $this->assertGuest();
        expect($user->fresh()->last_login_at)->toBeNull();

        $this->get('/admin/two-factor-challenge')
            ->assertOk()
            ->assertSee('name="code"', false)
            ->assertSee('name="recovery_code"', false);
    });

    it('signs in with a valid code', function () {
        [$user, $secret] = userWithTwoFactor();
        $this->post('/admin/login', ['email' => '2fa@example.com', 'password' => 'password']);

        $this->post('/admin/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
        expect($user->fresh()->last_login_at)->not->toBeNull();
    });

    it('signs in with a recovery code', function () {
        [$user] = userWithTwoFactor();
        $this->post('/admin/login', ['email' => '2fa@example.com', 'password' => 'password']);

        $this->post('/admin/two-factor-challenge', ['recovery_code' => 'recovery-code-1'])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($user);
    });

    it('never challenges a deactivated user', function () {
        [$user] = userWithTwoFactor();
        $user->forceFill(['is_active' => false])->save();

        $this->post('/admin/login', ['email' => '2fa@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account has been deactivated.']);
    });
});

describe('password reset', function () {
    it('shows the forgot-password page and sends a reset link', function () {
        Notification::fake();
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->get('/admin/forgot-password')->assertOk()->assertSee('name="email"', false);

        $this->post('/admin/forgot-password', ['email' => 'admin@example.com'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    });

    it('shows the reset page and saves the new password', function () {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $token = Password::createToken($user);

        $this->get("/admin/reset-password/{$token}?email=admin@example.com")
            ->assertOk()
            ->assertSee('value="'.$token.'"', false)
            ->assertSee('value="admin@example.com"', false);

        $this->post('/admin/reset-password', [
            'token' => $token,
            'email' => 'admin@example.com',
            'password' => 'a-new-Password-123',
            'password_confirmation' => 'a-new-Password-123',
        ])->assertRedirect(route('login'));

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'a-new-Password-123']);
        $this->assertAuthenticatedAs($user);
    });
});

describe('email verification', function () {
    it('shows the verification notice and resends the link', function () {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/admin/email/verify')
            ->assertOk()
            ->assertSee($user->email);

        $this->actingAs($user)->post('/admin/email/verification-notification')
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    });
});

it('shows the password confirmation page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/user/confirm-password')
        ->assertOk()
        ->assertSee('name="password"', false);
});
