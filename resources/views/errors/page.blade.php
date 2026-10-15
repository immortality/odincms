{{--
    An error page (403, 404, 419, 429): in the admin's look under /{admin_prefix}, in the theme's look elsewhere.
    $code, $title, $text
--}}
@php
    $admin = trim((string) config('cms.admin_prefix'), '/');
@endphp

@if (request()->is($admin, $admin.'/*'))
    <x-admin-layout::auth :title="$title">
        <div class="text-center">
            <div class="display-3 fw-bold mb-2" style="color: var(--primary)">{{ $code }}</div>
            <p class="mb-4" style="color: var(--text-muted)">{{ $text }}</p>
            <a href="{{ auth()->check() ? route('admin.dashboard') : route('login') }}" class="btn-brand btn-lg btn-block">
                {{ auth()->check() ? __('Back to the dashboard') : __('Sign in') }}
            </a>
        </div>
    </x-admin-layout::auth>
@else
    @include('themes.'.config('cms.theme').'.error', ['code' => $code, 'title' => $title, 'text' => $text])
@endif
