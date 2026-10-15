@php
    $recovery = $errors->has('recovery_code');
@endphp

<x-admin-layout::auth :title="__('Two-factor authentication')">
    <x-slot:description>
        <span data-tfa="code" @if ($recovery) hidden @endif>{{ __('Enter the 6-digit code from your authenticator app.') }}</span>
        <span data-tfa="recovery" @if (! $recovery) hidden @endif>{{ __('Enter one of your emergency recovery codes.') }}</span>
    </x-slot:description>

    <form method="POST" action="{{ route('two-factor.login') }}" class="auth-form needs-validation" novalidate
        data-auth-form data-loading-text="{{ __('Signing in…') }}">
        @csrf

        {{-- Only the visible field is enabled, so only it is checked and posted. --}}
        <div data-tfa="code" @if ($recovery) hidden @endif>
            <x-admin.auth.input name="code" :label="__('Code')" icon="fas fa-mobile-alt" :feedback="__('Please enter the code.')"
                inputmode="numeric" autocomplete="one-time-code" required :disabled="$recovery" :autofocus="! $recovery" />
        </div>
        <div data-tfa="recovery" @if (! $recovery) hidden @endif>
            <x-admin.auth.input name="recovery_code" :label="__('Recovery code')" icon="fas fa-key" :feedback="__('Please enter the code.')"
                autocomplete="one-time-code" required :disabled="! $recovery" :autofocus="$recovery" />
        </div>

        <x-admin.auth.submit>{{ __('Sign in') }}</x-admin.auth.submit>
    </form>

    <x-slot:note>
        <a href="#" data-tfa="code" data-tfa-show="recovery" @if ($recovery) hidden @endif>{{ __('Use a recovery code') }}</a>
        <a href="#" data-tfa="recovery" data-tfa-show="code" @if (! $recovery) hidden @endif>{{ __('Use an authentication code') }}</a>
    </x-slot:note>

    <script>
        // Switch between the authenticator code and a recovery code.
        document.querySelectorAll('[data-tfa-show]').forEach((link) => link.addEventListener('click', (event) => {
            event.preventDefault();
            const mode = link.dataset.tfaShow;
            document.querySelectorAll('[data-tfa]').forEach((el) => {
                el.hidden = el.dataset.tfa !== mode;
                el.querySelectorAll('input').forEach((input) => { input.disabled = el.hidden; });
            });
            document.querySelector('[data-tfa="' + mode + '"] input')?.focus();
        }));
    </script>
</x-admin-layout::auth>
