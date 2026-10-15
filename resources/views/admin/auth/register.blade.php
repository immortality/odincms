<x-admin-layout::auth :title="__('Create an account')">
    <form method="POST" action="{{ route('register') }}" class="auth-form needs-validation" novalidate
        data-auth-form data-loading-text="{{ __('Please wait…') }}">
        @csrf

        <x-admin.auth.input name="name" :label="__('Name')" icon="far fa-user" :feedback="__('Please enter your name.')"
            autocomplete="name" required autofocus />
        <x-admin.auth.input name="email" type="email" :label="__('Email')" icon="far fa-envelope" placeholder="name@company.com"
            :feedback="__('Please enter your email address.')" autocomplete="username" required />
        <x-admin.auth.input name="password" type="password" :label="__('Password')" :feedback="__('Please enter your password.')"
            autocomplete="new-password" required />
        <x-admin.auth.input name="password_confirmation" type="password" :label="__('Confirm password')"
            :feedback="__('Please repeat the password.')" autocomplete="new-password" required />

        <x-admin.auth.submit>{{ __('Create an account') }}</x-admin.auth.submit>
    </form>

    <x-slot:note>
        {{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
    </x-slot:note>
</x-admin-layout::auth>
