<x-admin-layout::auth :title="__('Choose a new password')">
    <form method="POST" action="{{ route('password.update') }}" class="auth-form needs-validation" novalidate
        data-auth-form data-loading-text="{{ __('Please wait…') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-admin.auth.input name="email" type="email" :label="__('Email')" icon="far fa-envelope" :value="$request->query('email')"
            :feedback="__('Please enter your email address.')" autocomplete="username" required />
        <x-admin.auth.input name="password" type="password" :label="__('New password')" :feedback="__('Please enter your password.')"
            autocomplete="new-password" required autofocus />
        <x-admin.auth.input name="password_confirmation" type="password" :label="__('Confirm the new password')"
            :feedback="__('Please repeat the password.')" autocomplete="new-password" required />

        <x-admin.auth.submit>{{ __('Save the new password') }}</x-admin.auth.submit>
    </form>

    <x-slot:note>
        <a href="{{ route('login') }}"><i class="fas fa-arrow-left me-1"></i> {{ __('Back to sign in') }}</a>
    </x-slot:note>
</x-admin-layout::auth>
