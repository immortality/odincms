<x-admin-layout::auth :title="__('Confirm your password')">
    <x-slot:description>{{ __('This is a secure area. Please confirm your password to continue.') }}</x-slot:description>

    <form method="POST" action="{{ route('password.confirm') }}" class="auth-form needs-validation" novalidate
        data-auth-form data-loading-text="{{ __('Please wait…') }}">
        @csrf

        <x-admin.auth.input name="password" type="password" :label="__('Password')" :feedback="__('Please enter your password.')"
            autocomplete="current-password" required autofocus />

        <x-admin.auth.submit>{{ __('Confirm') }}</x-admin.auth.submit>
    </form>
</x-admin-layout::auth>
