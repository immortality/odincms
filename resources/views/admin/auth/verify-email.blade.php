<x-admin-layout::auth :title="__('Verify your email address')">
    <x-slot:description>{{ __('Before you continue, please open the verification link we sent to :email.', ['email' => auth()->user()?->email]) }}</x-slot:description>

    <p class="text-center mb-3" style="font-size:.85rem;color:var(--text-muted);">{{ __('Did not get the email? We can send you another one.') }}</p>

    <form method="POST" action="{{ route('verification.send') }}" class="auth-form needs-validation" novalidate data-auth-form data-loading-text="{{ __('Please wait…') }}">
        @csrf
        <x-admin.auth.submit>{{ __('Send the link again') }}</x-admin.auth.submit>
    </form>

    <x-slot:note>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-ghost">{{ __('Sign out') }}</button>
        </form>
    </x-slot:note>
</x-admin-layout::auth>
