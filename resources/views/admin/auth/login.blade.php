<x-admin-layout::auth :title="__('Sign in')">
    <x-slot:description>{{ __('Access the :app admin panel with your email and password.', ['app' => config('app.name')]) }}</x-slot:description>

    {{-- AuthForm (odinadmin/js/auth.js): checks the fields, shows the spinner, then posts the form. --}}
    <form method="POST" action="{{ route('login') }}" class="auth-form needs-validation" novalidate
        data-auth-form data-loading-text="{{ __('Signing in…') }}">
        @csrf

        <div class="alert-soft danger alert-sm mb-3" role="alert" data-auth-error @if (! $errors->any()) hidden @endif>
            <i class="fas fa-times-circle"></i>
            <div class="alert-body">{{ $errors->first() }}</div>
        </div>

        <div class="form-group">
            <div class="form-label-row">
                <label class="form-label" for="login-email">{{ __('Email') }}</label>
            </div>
            <div class="input-icon icon-end icon-lg">
                <input type="email" class="form-control form-soft form-lg" id="login-email" name="email" value="{{ old('email') }}"
                    placeholder="name@company.com" autocomplete="username" required autofocus>
                <i class="far fa-envelope" aria-hidden="true"></i>
                <div class="invalid-feedback">{{ __('Please enter your email address.') }}</div>
            </div>
        </div>

        <div class="form-group">
            <div class="form-label-row">
                <label class="form-label" for="login-password">{{ __('Password') }}</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="form-link">{{ __('Forgot password?') }}</a>
                @endif
            </div>
            <div class="input-action icon-lg">
                <input type="password" class="form-control form-soft form-lg" id="login-password" name="password"
                    placeholder="{{ __('Enter your password') }}" autocomplete="current-password" required>
                <button type="button" class="input-action-btn" data-toggle-password="#login-password" aria-label="{{ __('Show password') }}"><i class="far fa-eye"></i></button>
                <div class="invalid-feedback">{{ __('Please enter your password.') }}</div>
            </div>
        </div>

        <div class="form-group">
            <label class="check-row mb-0">
                <input type="checkbox" class="form-check-input check-soft" name="remember" value="1" @checked(old('remember'))> {{ __('Keep me signed in') }}
            </label>
        </div>

        <button type="submit" class="btn-brand btn-lg btn-block" data-auth-submit>
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" hidden></span>
            <span data-label>{{ __('Sign in') }}</span>
        </button>
    </form>

    <x-slot:note>
        @if (Route::has('register'))
            {{ __('No account yet?') }} <a href="{{ route('register') }}">{{ __('Create an account') }}</a>
        @else
            {{ __('Don’t have an account? Ask your site administrator for an invitation.') }}
        @endif
    </x-slot:note>
</x-admin-layout::auth>
