{{--
    Auth pages in the OdinAdmin look (reference: odinadmin/login2.html): logo on top, the form in a
    centered card, an optional note under the card, the footer with the language drop-up.

    <x-admin-layout::auth :title="__('Sign in')">
        <x-slot:description>…</x-slot:description>   (optional, under the title)
        …form…
        <x-slot:note>…</x-slot:note>                 (optional, under the card)
    </x-admin-layout::auth>
--}}
@props([
    'title' => null,
])

@php
    $version = config('cms.odinadmin_version');
    $odin = fn (string $path) => asset('odinadmin/'.$path).'?ver='.$version;

    $locales = (array) config('cms.locales');
    $names = (array) config('cms.locale_names');
    $current = app()->getLocale();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $current) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <title>{{ $title ? $title.' | ' : '' }}{{ config('app.name') }} Admin</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ $odin('css/style.css') }}">
</head>

<body class="auth-page auth-page-soft">
    <main class="auth-center">

        <a href="{{ url('/') }}" class="auth-brand" aria-label="{{ config('app.name') }}">
            <span class="logo"><i class="fas fa-bolt"></i></span>
            <span>{{ config('app.name') }}</span>
        </a>

        <div class="auth-main">
            <section class="auth-card" @if ($title) aria-labelledby="auth-title" @endif>
                @if ($title)
                    <h1 class="auth-title" id="auth-title">{{ $title }}</h1>
                @endif
                @isset($description)
                    <p class="auth-desc">{{ $description }}</p>
                @endisset

                @include('admin.partials.flash', ['summary' => false])

                {{ $slot }}
            </section>

            @isset($note)
                <div class="auth-note">{{ $note }}</div>
            @endisset
        </div>

        <footer class="auth-footer">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
            @if (count($locales) > 1)
                <nav aria-label="{{ __('Language') }}">
                    <span class="dropup">
                        <button class="auth-lang" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Language') }}">
                            <span class="lang-code">{{ strtoupper($current) }}</span> {{ $names[$current] ?? strtoupper($current) }}
                            <i class="fas fa-chevron-up" style="font-size:.6rem"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end card-action-menu lang-menu">
                            @foreach ($locales as $locale)
                                <li>
                                    <a @class(['dropdown-item', 'active' => $locale === $current]) href="{{ request()->fullUrlWithQuery(['locale' => $locale]) }}" lang="{{ $locale }}">
                                        <span class="lang-code">{{ strtoupper($locale) }}</span><span>{{ $names[$locale] ?? strtoupper($locale) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </span>
                </nav>
            @endif
        </footer>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ $odin('js/app.js') }}"></script>
    <script src="{{ $odin('js/alerts.js') }}"></script>
    <script src="{{ $odin('js/forms.js') }}"></script>
    <script src="{{ $odin('js/auth.js') }}"></script>
</body>

</html>
