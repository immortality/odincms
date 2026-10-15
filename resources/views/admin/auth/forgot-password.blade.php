<x-admin-layout::auth :title="__('Reset password')">
    <x-slot:description>{{ __('Enter your email address and we will send you a link to choose a new password.') }}</x-slot:description>

    <form method="POST" action="{{ route('password.email') }}" class="auth-form needs-validation" novalidate
        data-auth-form data-loading-text="{{ __('Please wait…') }}">
        @csrf

        <x-admin.auth.input name="email" type="email" :label="__('Email')" icon="far fa-envelope" placeholder="name@company.com"
            :feedback="__('Please enter your email address.')" autocomplete="username" required autofocus />

        <x-admin.auth.submit>{{ __('Send reset link') }}</x-admin.auth.submit>
    </form>

    <x-slot:note>
        <a href="{{ route('login') }}"><i class="fas fa-arrow-left me-1"></i> {{ __('Back to sign in') }}</a>
    </x-slot:note>
</x-admin-layout::auth>
