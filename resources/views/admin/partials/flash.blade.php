{{--
    Flash messages in the OdinAdmin look: session('success' | 'error' | 'warning' | 'info'), Fortify's
    session('status'), plus a validation summary. Needs public/odinadmin/js/alerts.js for the close buttons.

    @include('admin.partials.flash', ['summary' => false])
--}}
@php
    $types = [
        'success' => ['success', 'fa-check-circle'],
        'error' => ['danger', 'fa-times-circle'],
        'warning' => ['warning', 'fa-exclamation-triangle'],
        'info' => ['info', 'fa-info-circle'],
    ];

    // Fortify sends either a translated message or one of these codes.
    $statusMessages = [
        'verification-link-sent' => __('A new verification link has been sent to your email address.'),
        'two-factor-authentication-enabled' => __('Scan the QR code with your authenticator app, then enter a code to finish enabling two-factor authentication.'),
        'two-factor-authentication-confirmed' => __('Two-factor authentication is now enabled.'),
        'two-factor-authentication-disabled' => __('Two-factor authentication has been disabled.'),
        'recovery-codes-generated' => __('New recovery codes have been generated.'),
        'profile-information-updated' => __('Your profile has been updated.'),
        'password-updated' => __('Your password has been changed.'),
    ];
    $status = session('status');

    $messages = [];
    if (is_string($status) && $status !== '') {
        $messages[] = ['success', 'fa-check-circle', $statusMessages[$status] ?? $status];
    }
    foreach ($types as $key => [$style, $icon]) {
        if (session()->has($key)) {
            $messages[] = [$style, $icon, session($key)];
        }
    }
    // Pass ['summary' => false] where the field errors are enough (the short auth forms).
    if (($summary ?? true) && $errors->any()) {
        $messages[] = ['danger', 'fa-times-circle', $errors->count() === 1 ? $errors->first() : __('Please fix the errors below.')];
    }
@endphp

@if ($messages)
    <div class="mb-3">
        @foreach ($messages as [$style, $icon, $text])
            <div class="alert-soft {{ $style }} alert-sm" role="alert">
                <i class="fas {{ $icon }}"></i>
                <div class="alert-body">{{ $text }}</div>
                <button type="button" class="alert-close" data-dismiss="alert" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
            </div>
        @endforeach
    </div>
@endif
