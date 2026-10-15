<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        $this->registerViews();

        // Also used by the two-factor redirect, so inactive users never reach the 2FA challenge.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('email', Str::lower($request->string(Fortify::username())->toString()))->first();

            if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
                return null;
            }

            if (! $user->is_active) {
                event(new Failed((string) config('fortify.guard'), $user, [Fortify::username() => $user->email]));

                throw ValidationException::withMessages([
                    Fortify::username() => __('This account has been deactivated.'),
                ]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            // Too many attempts: log the lockout and show the error on the login form (not a bare 429 page).
            return Limit::perMinute(5)->by($throttleKey)->response(function (Request $request, array $headers) {
                event(new Lockout($request));

                $seconds = (int) ($headers['Retry-After'] ?? 60);

                throw ValidationException::withMessages([
                    Fortify::username() => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
                ]);
            });
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }

    /**
     * The auth pages use the OdinAdmin auth layout (resources/views/admin/auth, layouts/auth).
     */
    private function registerViews(): void
    {
        Fortify::loginView(fn () => view('admin.auth.login'));
        // Only routed when Settings → Users → "Allow public registration" is on (ApplySettings).
        Fortify::registerView(fn () => view('admin.auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('admin.auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('admin.auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('admin.auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('admin.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('admin.auth.confirm-password'));
    }
}
